<?php

namespace App\Jobs;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\PipelineUsageLog;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Models\Workspace;
use App\Notifications\CreditsLow;
use App\Services\Ai\AiServiceManager;
use App\Services\Ai\FakePipelineAi;
use App\Services\Billing\WorkspaceCredits;
use App\Services\Matching\CareerGapGate;
use App\Services\Matching\ExperienceCalculator;
use App\Services\Matching\ExperienceGate;
use App\Services\Matching\MatchRubric;
use App\Services\Matching\SkillsGate;
use App\Services\Pipeline\BatchStatusRecomputer;
use App\Services\Pipeline\CandidateMatchWriter;
use App\Services\ResumeParsing\Exceptions\LowParseConfidenceException;
use App\Services\ResumeParsing\FileProcessor;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\ResumeParsing\ResumeParserManager;
use App\Support\Hiremium;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The per-CV pipeline: extract text → parse → find/create candidate → score
 * against the job → persist the match. One credit is charged up-front and
 * refunded on failure. tries=1 (no auto-retry) so a credit is never double
 * charged; retries are explicit (batch "Retry failed" / resume command).
 */
class ProcessResumeFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $resumeFileId) {}

    public function handle(FileProcessor $fileProcessor, ResumeParserManager $parser, AiServiceManager $ai, WorkspaceCredits $credits, CandidateMatchWriter $writer, BatchStatusRecomputer $recomputer): void
    {
        // One worker per file at a time. A duplicate delivery (queue redelivery,
        // resume-processing re-dispatch, retry clicked while a run is queued) would
        // otherwise re-parse and re-score — safe for credits (chargeOnce) but a
        // straight waste of AI tokens. If another worker holds the lock, exit now;
        // the TTL equals the job timeout so a crashed holder can't deadlock it.
        $lock = Cache::lock('cv-file:'.$this->resumeFileId, $this->timeout);
        if (! $lock->get()) {
            Log::info('ProcessResumeFileJob skipped: another worker is processing this file', ['resume_file_id' => $this->resumeFileId]);

            return;
        }

        try {
            $this->processFile($fileProcessor, $parser, $ai, $credits, $writer, $recomputer);
        } finally {
            $lock->release();
        }
    }

    private function processFile(FileProcessor $fileProcessor, ResumeParserManager $parser, AiServiceManager $ai, WorkspaceCredits $credits, CandidateMatchWriter $writer, BatchStatusRecomputer $recomputer): void
    {
        $file = ResumeFile::with('batch')->find($this->resumeFileId);
        if (! $file || $file->status === ResumeFile::STATUS_ANALYZED) {
            return;
        }

        $batch = $file->batch;
        if (! $batch) {
            return;
        }

        // Score against the job as it was when the batch was created (frozen
        // snapshot) — the user may have edited the live job since.
        $job = $batch->jobForAnalysis();
        $workspace = Workspace::find($batch->workspace_id);
        if (! $job || ! $workspace) {
            $this->markFailed($file, $batch, 'Job or workspace missing', $recomputer);

            return;
        }

        $cost = Hiremium::creditCostPerMatch();

        if (! $credits->canAfford($workspace, $cost)) {
            $this->markFailed($file, $batch, 'Insufficient credits', $recomputer, ResumeFile::FAIL_CREDITS);

            return;
        }

        $candidate = null;
        $stage = PipelineUsageLog::OP_PARSE;

        try {
            // Idempotent: if this file already carries an unrefunded charge (job ran
            // before and was killed/redelivered), do not charge again.
            $credits->chargeOnce($workspace, $cost, 'match', $file, $batch->created_by);

            $this->maybeNotifyCreditsLow($workspace, $credits);

            // 1) Parse the CV — or REUSE an existing parse. If these exact bytes
            //    were already parsed for another job in this workspace, the
            //    candidate's parsed_data is identical, so skip the parse call and
            //    only pay for the match. (Same file hash = same CV.)
            $t0 = microtime(true);
            $testing = FakePipelineAi::enabled();
            $known = $file->file_hash ? $writer->findCandidateByFileHash($workspace->id, $file->file_hash, $file->id) : null;
            $reused = $known && ! empty($known->parsed_data);

            if ($reused) {
                $data = (array) $known->parsed_data;

                PipelineUsageLog::record([
                    'workspace_id' => $workspace->id, 'resume_batch_id' => $batch->id, 'resume_file_id' => $file->id,
                    'operation' => PipelineUsageLog::OP_PARSE, 'lane' => PipelineUsageLog::LANE_LIVE,
                    'ai_provider' => 'reused', 'ai_model' => 'existing parse (same CV, candidate #'.$known->id.')',
                    'tokens_used' => 0, 'credits_charged' => 0,
                    'confidence' => ParseConfidence::fromData($data),
                    'duration_ms' => (int) ((microtime(true) - $t0) * 1000),
                ]);
            } else {
                $tmp = $this->toTempUploadedFile($file);
                $processed = $fileProcessor->process($tmp);
                // Testing mode (Site Settings): fake parse/score, no AI tokens spent.
                $parsed = $testing ? FakePipelineAi::parse($processed, $file->original_name) : $parser->parse($processed);
                @unlink($tmp->getRealPath());

                $data = $parsed->data;

                PipelineUsageLog::record([
                    'workspace_id' => $workspace->id, 'resume_batch_id' => $batch->id, 'resume_file_id' => $file->id,
                    'operation' => PipelineUsageLog::OP_PARSE, 'lane' => PipelineUsageLog::LANE_LIVE,
                    'ai_provider' => $parsed->provider, 'ai_model' => $parsed->model,
                    'tokens_used' => $parsed->tokensUsed, 'credits_charged' => 0,
                    'confidence' => $parsed->confidence(),
                    'duration_ms' => (int) ((microtime(true) - $t0) * 1000),
                ]);
            }

            // How sure the parser is that it read this CV correctly (null when
            // the model reported nothing). Kept on the file even if the match
            // below is skipped, so the recruiter sees why.
            $confidence = ParseConfidence::fromData($data);

            $file->update(['status' => ResumeFile::STATUS_PARSED, 'parse_confidence' => $confidence]);

            // 2) Find or create the candidate (file hash → email → phone).
            $candidate = $writer->resolveCandidate($workspace->id, $data, $file);
            $file->update(['candidate_id' => $candidate->id]);

            // 2b) Total experience: computed deterministically in code from the CV
            //     dates (not by the model) and stored on the candidate.
            $years = ExperienceCalculator::totalYears((array) ($data['experience'] ?? []));
            if ($years !== null) {
                $candidate->update(['experience_years' => (int) floor($years)]);
            }

            // 2c) Quality gate: a CV the parser could not read with enough
            //     certainty is never scored — a ranking built on data we don't
            //     trust is worse than no ranking. The parse itself is kept (and
            //     the credit refunded by the handler below).
            if (! ParseConfidence::isSufficient($confidence)) {
                throw new LowParseConfidenceException(ParseConfidence::failureMessage($confidence));
            }

            // 3) Collect the findings. The model reports evidence only — which
            //    required skills the work history backs, how many job-relevant
            //    years it shows, the education fit, the nice-to-haves it found
            //    and how the resume reads — and never the score.
            $stage = PipelineUsageLog::OP_MATCH;
            $t1 = microtime(true);
            $applicationText = $this->applicationText($data);
            $result = $testing
                ? FakePipelineAi::score($data, (array) ($job->must_have_skills ?? []), (array) ($job->nice_to_have_skills ?? []))
                : $ai->scoreDetailed($job->toMatchingText(), $applicationText);

            // 3b) The score itself: fixed-weight rubric arithmetic in code, with
            //     the career gap read off the CV dates (never asked of the model).
            $gap = ExperienceCalculator::careerGap((array) ($data['experience'] ?? []));
            $result = MatchRubric::apply($result, $job, $gap);

            // 3c) Hard caps (admin switches in AI Settings), each lowering the
            //     rubric total: below minimum experience, any unmet must-have
            //     skill, then a career gap.
            ['result' => $result] = ExperienceGate::apply($result, $years, $job);
            ['result' => $result] = SkillsGate::apply($result, $job);
            ['result' => $result] = CareerGapGate::apply($result, $gap);

            PipelineUsageLog::record([
                'workspace_id' => $workspace->id, 'resume_batch_id' => $batch->id, 'resume_file_id' => $file->id,
                'operation' => PipelineUsageLog::OP_MATCH, 'lane' => PipelineUsageLog::LANE_LIVE,
                'ai_provider' => $result->provider, 'ai_model' => $result->model,
                'tokens_used' => $result->tokensUsed, 'credits_charged' => $cost, 'score' => $result->score,
                'duration_ms' => (int) ((microtime(true) - $t1) * 1000),
            ]);

            $writer->writeMatch($file, $batch, $job, $candidate, $result, $cost, $data);

            $file->update(['status' => ResumeFile::STATUS_ANALYZED, 'error' => null, 'failure_kind' => null]);
        } catch (Throwable $e) {
            // Refund whatever this file still has outstanding (idempotent — safe even
            // if a previous run already refunded, or if this run didn't charge).
            try {
                $credits->refundOutstanding($workspace, 'match', $file, $batch->created_by);
            } catch (Throwable) {
                // best-effort refund
            }

            $file->update([
                'status' => ResumeFile::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 250),
                'failure_kind' => $e instanceof InsufficientCreditsException ? ResumeFile::FAIL_CREDITS : ResumeFile::FAIL_ERROR,
            ]);

            PipelineUsageLog::record([
                'workspace_id' => $workspace->id, 'resume_batch_id' => $batch->id, 'resume_file_id' => $file->id,
                'operation' => $stage, 'lane' => PipelineUsageLog::LANE_LIVE,
                'status' => PipelineUsageLog::STATUS_FAILED, 'credits_charged' => 0,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            if ($candidate) {
                $writer->writeFailedMatch($file, $batch, $job, $candidate);
            }
        } finally {
            $recomputer->recompute($batch);
        }
    }

    /**
     * Hard-kill safety net: the queue calls this when the job dies OUTSIDE the
     * try/catch above — the 180s timeout, a fatal, or a worker being killed
     * between charge and refund. Refund the outstanding charge (idempotent: only
     * if the ledger shows one), mark the file failed (retryable), recompute.
     */
    public function failed(?Throwable $e = null): void
    {
        $file = ResumeFile::with('batch')->find($this->resumeFileId);
        if (! $file || $file->status === ResumeFile::STATUS_ANALYZED) {
            return;
        }

        $batch = $file->batch;
        $workspace = $batch ? Workspace::find($batch->workspace_id) : null;

        if ($workspace) {
            try {
                app(WorkspaceCredits::class)->refundOutstanding($workspace, 'match', $file, $batch->created_by);
            } catch (Throwable) {
                // best-effort
            }
        }

        $file->update([
            'status' => ResumeFile::STATUS_FAILED,
            'failure_kind' => ResumeFile::FAIL_ERROR,
            'error' => mb_substr('Processing was interrupted'.($e ? ': '.$e->getMessage() : '').' — retry to run again.', 0, 250),
        ]);

        if ($batch) {
            app(BatchStatusRecomputer::class)->recompute($batch);
        }
    }

    private function markFailed(ResumeFile $file, ResumeBatch $batch, string $message, BatchStatusRecomputer $recomputer, string $kind = ResumeFile::FAIL_ERROR): void
    {
        $file->update(['status' => ResumeFile::STATUS_FAILED, 'error' => $message, 'failure_kind' => $kind]);
        $recomputer->recompute($batch);
    }

    private function toTempUploadedFile(ResumeFile $file): UploadedFile
    {
        $contents = Storage::disk($file->disk)->get($file->storage_key);
        $tmp = tempnam(sys_get_temp_dir(), 'cv_');
        file_put_contents($tmp, (string) $contents);

        return new UploadedFile($tmp, $file->original_name, $file->mime ?: null, null, true);
    }

    private function applicationText(array $d): string
    {
        $p = [];
        if (! empty($d['fullName'])) {
            $p[] = 'Name: '.$d['fullName'];
        }
        if (! empty($d['jobTitle'])) {
            $p[] = 'Current title: '.$d['jobTitle'];
        }
        if (! empty($d['summary'])) {
            $p[] = 'Summary: '.$d['summary'];
        }
        if (! empty($d['skills'])) {
            $p[] = 'Skills: '.implode(', ', (array) $d['skills']);
        }
        foreach ((array) ($d['experience'] ?? []) as $e) {
            $p[] = 'Experience: '.trim(($e['title'] ?? '').' at '.($e['company'] ?? '').' ('.($e['startDate'] ?? '').' - '.($e['endDate'] ?? '').')');
            if (! empty($e['description'])) {
                $p[] = $e['description'];
            }
        }
        foreach ((array) ($d['education'] ?? []) as $ed) {
            $p[] = 'Education: '.trim(($ed['degree'] ?? '').' '.($ed['field'] ?? '').' at '.($ed['institution'] ?? ''));
        }

        return implode("\n", $p) ?: 'No parseable resume content.';
    }

    /** Notify the workspace owner once/day when the balance drops to the low threshold. */
    private function maybeNotifyCreditsLow(Workspace $workspace, WorkspaceCredits $credits): void
    {
        $threshold = (int) config('hiremium.credits.low_threshold', 10);
        $total = $credits->balance($workspace)['total'];

        if ($total <= 0 || $total > $threshold) {
            return;
        }
        if (! Cache::add("credits_low_{$workspace->id}", true, now()->addDay())) {
            return;
        }

        $workspace->owner?->notify(new CreditsLow($total));
    }
}
