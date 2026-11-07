<?php

namespace App\Console\Commands;

use App\Services\Pipeline\BatchProcessor;
use Illuminate\Console\Command;

/**
 * Re-dispatch unfinished resume files for batches still processing, so a batch
 * automatically resumes if a cron worker was interrupted. Scheduled + idempotent.
 */
class ResumeProcessing extends Command
{
    protected $signature = 'hiremium:resume-processing';

    protected $description = 'Re-dispatch unfinished resume files for batches still processing';

    public function handle(BatchProcessor $processor): int
    {
        $count = $processor->resumeStalled();

        $this->info("Re-dispatched {$count} file(s).");

        return self::SUCCESS;
    }
}
