<?php

namespace App\Console\Commands;

use App\Models\ProviderBatch;
use App\Services\Pipeline\ProviderBatchIngestor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Advances every pending provider Batch API submission: polls its status and,
 * when complete, ingests results into candidates + matches. Scheduled every few
 * minutes; withoutOverlapping guards against concurrent cron runs.
 */
class PollProviderBatches extends Command
{
    protected $signature = 'hiremium:poll-batches';

    protected $description = 'Poll pending provider Batch API submissions and ingest completed results.';

    public function handle(ProviderBatchIngestor $ingestor): int
    {
        $pending = ProviderBatch::whereIn('status', [
            ProviderBatch::STATUS_SUBMITTED,
            ProviderBatch::STATUS_PROCESSING,
        ])->get();

        if ($pending->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($pending as $pb) {
            try {
                $ingestor->process($pb);
            } catch (Throwable $e) {
                Log::error('Provider batch poll failed', ['provider_batch_id' => $pb->id, 'error' => $e->getMessage()]);
                $this->error("Batch #{$pb->id}: {$e->getMessage()}");
            }
        }

        $this->info("Polled {$pending->count()} provider batch(es).");

        return self::SUCCESS;
    }
}
