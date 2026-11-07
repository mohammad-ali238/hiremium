<?php

namespace App\Jobs;

use App\Models\Job;
use App\Models\ResumeBatch;
use App\Models\Workspace;
use App\Services\Activity\ActivityLogger;
use App\Services\Pipeline\BatchProcessor;
use App\Services\Uploads\ResumeUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued intake: the HTTP upload only stores the raw files/ZIPs and creates one
 * "extracting" batch, then returns instantly. This job does the heavy part —
 * expand ZIPs, validate, dedupe, enforce intake ceilings, split into batches —
 * and kicks off AI processing for every batch it produced. Safe to run for
 * minutes; no request timeout involved.
 */
class ExtractUploadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800; // big ZIPs on shared hosting

    /**
     * @param  array<int, array{path: string, name: string}>  $items  raw stored uploads (disk paths)
     * @param  array<int, array{name: string, reason: string}>  $preRejected
     */
    public function __construct(
        public int $batchId,
        public array $items,
        public array $preRejected,
        public int $maxFiles,
        public int $maxFileMb,
    ) {}

    public function handle(ResumeUploadService $service, BatchProcessor $processor): void
    {
        // Atomic claim: flip intake.claimed in one conditional UPDATE so a duplicate
        // delivery (queue redelivery while a long extraction is running) sees zero
        // rows affected and exits — it must never extract the same upload twice.
        // Portable (MySQL + SQLite): a conditional UPDATE is atomic — only one worker
        // can move the row from 'extracting' to 'extracting_claimed'.
        $claimed = ResumeBatch::whereKey($this->batchId)
            ->where('status', ResumeBatch::STATUS_EXTRACTING)
            ->update(['status' => ResumeBatch::STATUS_EXTRACTING_CLAIMED]);

        $batch = $claimed ? ResumeBatch::find($this->batchId) : null;
        if (! $batch) {
            // Not ours: another worker owns it, it was deleted, or already extracted.
            // Do NOT clean the raw files here — the owning worker still needs them.
            return;
        }

        $workspace = Workspace::find($batch->workspace_id);
        $job = Job::find($batch->job_id);
        if (! $workspace || ! $job) {
            $batch->update(['status' => ResumeBatch::STATUS_FAILED, 'intake' => ['error' => 'Job or workspace missing']]);
            $this->cleanupRaw();

            return;
        }

        try {
            ['batches' => $batches, 'result' => $r] = $service->process(
                $workspace, $job, $this->items, (int) $batch->created_by, $this->maxFiles, $this->maxFileMb,
                $this->preRejected, $batch, $batch->upload_group_id
            );
        } catch (Throwable $e) {
            $batch->update(['status' => ResumeBatch::STATUS_FAILED, 'intake' => ['error' => 'Extraction failed: '.mb_substr($e->getMessage(), 0, 200)]]);
            $this->cleanupRaw();

            throw $e;
        }

        $this->cleanupRaw();

        // Summary for the batches page (what landed where, and why anything was trimmed).
        $batch->refresh()->update(['intake' => [
            'done' => true,
            'stored' => $r['stored'],
            'batches' => $r['batches'],
            'duplicates' => $r['duplicates'],
            'invalid' => $r['invalid'],
            'capped' => (bool) ($r['capped'] ?? false),
            'batch_ids' => array_map(fn ($b) => $b->id, $batches),
        ]]);

        ActivityLogger::log('batch.uploaded', $batch, "Uploaded {$r['stored']} CV(s) to \"{$job->title}\"".($r['batches'] > 1 ? " across {$r['batches']} batches" : ''));

        foreach ($batches as $b) {
            if ($b->total_files > 0) {
                $processor->start($b);
            }
        }
    }

    public function failed(?Throwable $e = null): void
    {
        $batch = ResumeBatch::find($this->batchId);
        if ($batch && in_array($batch->status, [ResumeBatch::STATUS_EXTRACTING, ResumeBatch::STATUS_EXTRACTING_CLAIMED], true)) {
            $batch->update(['status' => ResumeBatch::STATUS_FAILED, 'intake' => ['error' => 'Extraction was interrupted'.($e ? ': '.mb_substr($e->getMessage(), 0, 160) : '')]]);
        }
        $this->cleanupRaw();
    }

    /** Remove the raw upload staging files (they live under the private disk). */
    private function cleanupRaw(): void
    {
        foreach ($this->items as $item) {
            try {
                if (! empty($item['path']) && is_file($item['path'])) {
                    @unlink($item['path']);
                }
            } catch (Throwable) {
                // best-effort
            }
        }
        // remove the staging dir if empty
        $dir = $this->items[0]['path'] ?? null;
        if ($dir) {
            @rmdir(dirname($dir));
        }
    }
}
