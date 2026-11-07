<?php

namespace App\Services\Pipeline;

use App\Models\AiSetting;
use App\Models\PipelineUsageLog;
use App\Models\ProviderBatch;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Models\Workspace;
use App\Services\Ai\Batch\BatchScoringManager;
use App\Services\Ai\Batch\CombinedMatchPrompt;
use App\Services\Billing\WorkspaceCredits;
use App\Services\Matching\CareerGapGate;
use App\Services\Matching\ExperienceCalculator;
use App\Services\Matching\ExperienceGate;
use App\Services\Matching\MatchRubric;
use App\Services\Matching\SkillsGate;
use App\Services\ResumeParsing\ParseConfidence;
use Throwable;

/**
 * Polls one async provider batch and, once complete, ingests its results into
 * candidates + matches (charging a credit per successful CV) using the same
 * writer as the live lane. Idempotent: already-analyzed files are skipped.
 */
class ProviderBatchIngestor
{
    public function __construct(
        private BatchScoringManager $manager,
        private WorkspaceCredits $credits,
        private CandidateMatchWriter $writer,
        private BatchStatusRecomputer $recomputer,
    ) {}

    public function process(ProviderBatch $pb): void
    {
        $settings = AiSetting::current();
        $driver = $this->manager->driver($pb->provider);

        $poll = $driver->poll($pb, $settings);

        $pb->update([
            'status' => $poll['status'],
            'output_file_id' => $poll['output_file_id'] ?? $pb->output_file_id,
            'completed_count' => $poll['completed'],
            'failed_count' => $poll['failed'],
            'error' => $poll['error'] ?? $pb->error,
        ]);

        $resumeBatch = $pb->resumeBatch;
        if (! $resumeBatch) {
            return;
        }

        if ($poll['status'] === ProviderBatch::STATUS_COMPLETED) {
            $this->ingest($pb, $driver, $settings, $resumeBatch);
        } elseif (in_array($poll['status'], [ProviderBatch::STATUS_FAILED, ProviderBatch::STATUS_CANCELLED], true)) {
            $this->failRemaining($pb, $poll['error'] ?? 'Provider batch did not complete');
            $this->recomputer->recompute($resumeBatch);
        }
    }

    private function ingest(ProviderBatch $pb, $driver, AiSetting $settings, ResumeBatch $resumeBatch): void
    {
        $results = $driver->results($pb, $settings);
        $job = $resumeBatch->jobForAnalysis(); // frozen snapshot, not the live job
        $workspace = Workspace::find($resumeBatch->workspace_id);
        $cost = $pb->cost_per_item;
        $fileIds = (array) ($pb->meta['file_ids'] ?? []);

        foreach ($results as $r) {
            $fileId = $this->fileIdFromCustomId($r->customId);
            if (! $fileId || ! in_array($fileId, $fileIds, true)) {
                continue;
            }

            $file = ResumeFile::find($fileId);
            if (! $file || $file->batch_id !== $resumeBatch->id || $file->status === ResumeFile::STATUS_ANALYZED) {
                continue;
            }

            if (! $r->ok) {
                $this->failFile($file, $r->error ?? 'No result from provider');

                continue;
            }

            $parsed = CombinedMatchPrompt::parse($r->content, $settings);
            if (! $parsed) {
                $this->failFile($file, 'Unparseable batch response');

                continue;
            }

            if (! $job || ! $workspace) {
                $this->failFile($file, 'Job or workspace missing');

                continue;
            }

            // The batch lane parses and scores in ONE call, so the confidence
            // arrives together with the score. Too low: throw the score away
            // (never charge for it) and report the CV as unread, exactly as the
            // live lane does before it scores.
            $confidence = ParseConfidence::fromData($parsed['data']);
            $file->update(['parse_confidence' => $confidence]);

            if (! ParseConfidence::isSufficient($confidence)) {
                $this->failFile($file, ParseConfidence::failureMessage($confidence), ResumeFile::FAIL_ERROR, $confidence);

                continue;
            }

            if (! $this->credits->canAfford($workspace, $cost)) {
                $this->failFile($file, 'Insufficient credits', ResumeFile::FAIL_CREDITS);

                continue;
            }

            try {
                $this->credits->chargeOnce($workspace, $cost, 'match', $file, $resumeBatch->created_by);
                $candidate = $this->writer->resolveCandidate($workspace->id, $parsed['data'], $file);

                // Total experience computed in code from the returned CV dates; the
                // job minimum was already in the prompt. Hard-cap if below minimum.
                $years = ExperienceCalculator::totalYears((array) ($parsed['data']['experience'] ?? []));
                if ($years !== null) {
                    $candidate->update(['experience_years' => (int) floor($years)]);
                }
                // The score is the rubric's, computed from the findings in the
                // combined response; the career gap comes off the CV dates.
                $gap = ExperienceCalculator::careerGap((array) ($parsed['data']['experience'] ?? []));
                $parsed['result'] = MatchRubric::apply($parsed['result'], $job, $gap);

                ['result' => $parsed['result']] = ExperienceGate::apply($parsed['result'], $years, $job, $settings);
                ['result' => $parsed['result']] = SkillsGate::apply($parsed['result'], $job, $settings);
                ['result' => $parsed['result']] = CareerGapGate::apply($parsed['result'], $gap, $settings);
                $file->update(['candidate_id' => $candidate->id, 'status' => ResumeFile::STATUS_PARSED]);
                $this->writer->writeMatch($file, $resumeBatch, $job, $candidate, $parsed['result'], $cost, $parsed['data']);
                $file->update(['status' => ResumeFile::STATUS_ANALYZED, 'error' => null, 'failure_kind' => null]);

                PipelineUsageLog::record([
                    'workspace_id' => $workspace->id, 'resume_batch_id' => $resumeBatch->id, 'resume_file_id' => $file->id,
                    'operation' => PipelineUsageLog::OP_COMBINED, 'lane' => PipelineUsageLog::LANE_BATCH,
                    'ai_provider' => $parsed['result']->provider, 'ai_model' => $parsed['result']->model,
                    'tokens_used' => $parsed['result']->tokensUsed, 'credits_charged' => $cost,
                    'score' => $parsed['result']->score, 'confidence' => $confidence,
                ]);
            } catch (Throwable $e) {
                try {
                    $this->credits->refundOutstanding($workspace, 'match', $file, $resumeBatch->created_by);
                } catch (Throwable) {
                    // best-effort refund
                }
                $this->failFile($file, $e->getMessage());
            }
        }

        // Any file that was submitted but never came back → mark failed so the batch converges.
        $this->failRemaining($pb, 'Missing from batch results');
        $this->recomputer->recompute($resumeBatch);
    }

    private function failRemaining(ProviderBatch $pb, string $message): void
    {
        $fileIds = (array) ($pb->meta['file_ids'] ?? []);
        if (empty($fileIds)) {
            return;
        }

        ResumeFile::whereIn('id', $fileIds)
            ->where('status', ResumeFile::STATUS_SUBMITTED)
            ->get()
            ->each(fn (ResumeFile $f) => $this->failFile($f, $message));
    }

    private function failFile(ResumeFile $file, string $message, string $kind = ResumeFile::FAIL_ERROR, ?float $confidence = null): void
    {
        $file->update(['status' => ResumeFile::STATUS_FAILED, 'error' => mb_substr($message, 0, 250), 'failure_kind' => $kind]);

        PipelineUsageLog::record([
            'workspace_id' => $file->workspace_id, 'resume_batch_id' => $file->batch_id, 'resume_file_id' => $file->id,
            'operation' => PipelineUsageLog::OP_COMBINED, 'lane' => PipelineUsageLog::LANE_BATCH,
            'status' => PipelineUsageLog::STATUS_FAILED, 'credits_charged' => 0,
            'confidence' => $confidence,
            'error_message' => mb_substr($message, 0, 500),
        ]);
    }

    private function fileIdFromCustomId(string $customId): ?int
    {
        return str_starts_with($customId, 'rf-') ? (int) substr($customId, 3) : null;
    }
}
