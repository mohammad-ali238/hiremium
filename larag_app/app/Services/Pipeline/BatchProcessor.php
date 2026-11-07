<?php

namespace App\Services\Pipeline;

use App\Jobs\ProcessResumeFileJob;
use App\Jobs\SubmitProviderBatchJob;
use App\Models\AiSetting;
use App\Models\CandidateJobMatch;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Services\Ai\Batch\BatchScoringManager;
use App\Support\Hiremium;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates a batch through the AI pipeline using a hybrid dispatch model.
 *
 * Up to config('hiremium.ai.batch_threshold') CVs of an upload go on the
 * immediate "live" lane and are drained right away by the cron worker. Anything
 * beyond the threshold spills onto a deferred, rate-limited "batch" lane
 * (staggered so a large import — e.g. 500 CVs — processes the first N straight
 * away and trickles the remainder through afterwards). Both lanes run the
 * identical per-CV pipeline (ProcessResumeFileJob), so the parse-resume and
 * match-score providers configured in AI Settings are honoured either way, and
 * the candidate list re-ranks by score as each result lands.
 */
class BatchProcessor
{
    private function queue(): string
    {
        return config('hiremium.queue.name', 'cv');
    }

    /** Begin processing: dispatch each not-yet-processed file across the two lanes. */
    public function start(ResumeBatch $batch): void
    {
        $files = $batch->files()
            ->where('status', ResumeFile::STATUS_UPLOADED)
            ->orderBy('id')
            ->get();

        if ($files->isEmpty()) {
            return;
        }

        $batch->update(['status' => ResumeBatch::STATUS_PROCESSING]);

        $this->dispatchHybrid($files);
    }

    /**
     * Dispatch a set of files across the live and batch lanes. The first
     * {batch_threshold} run immediately on the live lane. The overflow goes to
     * the async provider Batch API when the AI config supports it (one combined
     * parse+score request per CV), otherwise it falls back to the deferred,
     * staggered live queue.
     */
    private function dispatchHybrid(Collection $files): void
    {
        $threshold = Hiremium::batchThreshold();
        $queue = $this->queue();

        $ordered = $files->values();
        $live = $ordered->take($threshold);
        $overflow = $ordered->slice($threshold)->values();

        // Live lane — picked up on the next cron worker run.
        foreach ($live as $file) {
            ProcessResumeFileJob::dispatch($file->id)->onQueue($queue);
        }

        if ($overflow->isEmpty()) {
            return;
        }

        $batchId = (int) $overflow->first()->batch_id;

        // Batch lane — true async provider Batch API when eligible…
        if (app(BatchScoringManager::class)->isEligible(AiSetting::current())) {
            Log::info('Hybrid dispatch: overflow → provider Batch API', [
                'resume_batch_id' => $batchId, 'live' => $live->count(), 'overflow' => $overflow->count(),
            ]);

            SubmitProviderBatchJob::dispatch($batchId, $overflow->pluck('id')->all())->onQueue($queue);

            return;
        }

        // …otherwise the deferred, rate-limited live queue so the overflow
        // doesn't burst the provider API and the live lane finishes first.
        Log::info('Hybrid dispatch: overflow → throttled live lane (batch lane not eligible)', [
            'resume_batch_id' => $batchId, 'live' => $live->count(), 'overflow' => $overflow->count(),
        ]);

        $perMinute = Hiremium::batchLanePerMinute();
        foreach ($overflow as $i => $file) {
            ProcessResumeFileJob::dispatch($file->id)
                ->onQueue($queue)
                ->delay(now()->addMinutes(intdiv($i, $perMinute) + 1));
        }
    }

    /**
     * Re-run the files that failed for a real reason (parse/AI/infra). Files
     * waiting on credits are handled separately by retryCreditFailures() so a
     * generic retry doesn't re-fail them against an empty wallet.
     */
    public function retryFailed(ResumeBatch $batch): int
    {
        $failed = $batch->files()
            ->where('status', ResumeFile::STATUS_FAILED)
            ->where(fn ($q) => $q->whereNull('failure_kind')->orWhere('failure_kind', '!=', ResumeFile::FAIL_CREDITS))
            ->get();
        if ($failed->isEmpty()) {
            return 0;
        }

        foreach ($failed as $file) {
            $file->update(['status' => ResumeFile::STATUS_UPLOADED, 'error' => null, 'failure_kind' => null]);
            CandidateJobMatch::where('resume_file_id', $file->id)
                ->update(['processing_status' => CandidateJobMatch::PROC_PENDING]);
        }

        $batch->update(['status' => ResumeBatch::STATUS_PROCESSING]);

        $this->dispatchHybrid($failed);

        return $failed->count();
    }

    /**
     * Re-run ONLY the files that failed because the workspace ran out of credits
     * (the CVs themselves are fine). Meant to be triggered by the user after a
     * top-up — deliberately not automatic. Returns how many were re-dispatched.
     */
    public function retryCreditFailures(ResumeBatch $batch): int
    {
        $files = $batch->files()
            ->where('status', ResumeFile::STATUS_FAILED)
            ->where('failure_kind', ResumeFile::FAIL_CREDITS)
            ->get();
        if ($files->isEmpty()) {
            return 0;
        }

        foreach ($files as $file) {
            $file->update(['status' => ResumeFile::STATUS_UPLOADED, 'error' => null, 'failure_kind' => null]);
            CandidateJobMatch::where('resume_file_id', $file->id)
                ->update(['processing_status' => CandidateJobMatch::PROC_PENDING]);
        }

        $batch->update(['status' => ResumeBatch::STATUS_PROCESSING]);

        $this->dispatchHybrid($files);

        return $files->count();
    }

    /**
     * Resume-from-interruption: re-dispatch unfinished files for any batch still
     * marked processing (e.g. the cron worker died mid-run). Idempotent — the
     * job no-ops on already-analyzed files.
     *
     * @return int files re-dispatched
     */
    public function resumeStalled(): int
    {
        $count = 0;

        // Queued-intake batches whose extraction job never completed (queue row
        // lost, worker killed before failed() ran). The raw staging files went
        // with the job, so they can't be re-extracted: time them out with a clear
        // message so the user re-uploads instead of watching "Preparing…" forever.
        $staleExtract = max(1, (int) config('hiremium.uploads.extract_stale_hours', 2));
        ResumeBatch::whereIn('status', ResumeBatch::EXTRACTING_STATUSES)
            ->where('created_at', '<', now()->subHours($staleExtract))
            ->each(function (ResumeBatch $b) {
                $b->update([
                    'status' => ResumeBatch::STATUS_FAILED,
                    'intake' => array_merge((array) ($b->intake ?? []), ['error' => 'Extraction did not complete (the background job was lost). Please upload these files again.']),
                ]);
            });

        ResumeBatch::where('status', ResumeBatch::STATUS_PROCESSING)
            ->chunkById(50, function ($batches) use (&$count) {
                foreach ($batches as $batch) {
                    $stuck = $batch->files()
                        ->whereIn('status', [ResumeFile::STATUS_UPLOADED, ResumeFile::STATUS_PARSED])
                        ->get();

                    foreach ($stuck as $file) {
                        ProcessResumeFileJob::dispatch($file->id)->onQueue($this->queue());
                        $count++;
                    }

                    $count += $this->rescueStrandedSubmissions($batch);
                }
            });

        return $count;
    }

    /**
     * Files handed to a provider batch sit at `submitted` until the poller
     * ingests results. If the provider batch is gone (deleted), terminal
     * without ingesting them, or has been pending longer than the stale window,
     * nothing would ever process them — move them back to `uploaded` and run
     * them on the live lane. Returns how many were rescued.
     */
    public function rescueStrandedSubmissions(ResumeBatch $batch): int
    {
        $staleHours = max(1, (int) config('hiremium.ai.batch_stale_hours', 26));

        $submitted = $batch->files()->where('status', ResumeFile::STATUS_SUBMITTED)->get();
        if ($submitted->isEmpty()) {
            return 0;
        }

        // Which of these files still belong to a LIVE provider batch (pending and not stale)?
        $covered = [];
        foreach ($batch->providerBatches()->get() as $pb) {
            $alive = $pb->isPending() && $pb->created_at && $pb->created_at->gt(now()->subHours($staleHours));
            if ($alive) {
                foreach ((array) ($pb->meta['file_ids'] ?? []) as $id) {
                    $covered[(int) $id] = true;
                }
            }
        }

        $rescued = 0;
        foreach ($submitted as $file) {
            if (isset($covered[$file->id])) {
                continue; // a live provider batch will still deliver this one
            }
            $file->update(['status' => ResumeFile::STATUS_UPLOADED, 'error' => null]);
            ProcessResumeFileJob::dispatch($file->id)->onQueue($this->queue());
            $rescued++;
        }

        if ($rescued > 0) {
            Log::warning('Rescued stranded batch-lane files back to the live lane', [
                'resume_batch_id' => $batch->id, 'rescued' => $rescued,
            ]);
        }

        return $rescued;
    }
}
