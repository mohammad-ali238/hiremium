<?php

namespace App\Console\Commands;

use App\Jobs\Tools\RunToolStagesJob;
use App\Models\ToolRun;
use App\Services\Tools\ToolRunner;
use Illuminate\Console\Command;

/**
 * Finishes free-tool runs that stopped moving.
 *
 * A run is left "processing" whenever the thing that was driving it went away:
 * the visitor closed the tab before the fallback job was picked up, a worker
 * was killed mid-stage, or the queue was starved. This command picks those up
 * every few minutes — resuming from the stage they reached, never from the
 * start — and gives up on the ones that are genuinely stuck.
 */
class ToolsSweepCommand extends Command
{
    protected $signature = 'hiremium:tools-sweep
                            {--stale=10 : Minutes without progress before a run is resumed}
                            {--abandon=60 : Minutes after which a stuck run is marked failed}';

    protected $description = 'Resume or fail Free Tools runs that stopped making progress';

    public function handle(ToolRunner $runner): int
    {
        $stale = now()->subMinutes((int) $this->option('stale'));
        $abandon = now()->subMinutes((int) $this->option('abandon'));

        $runs = ToolRun::query()
            ->where('status', ToolRun::STATUS_PROCESSING)
            ->where('updated_at', '<', $stale)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $resumed = 0;
        $failed = 0;

        foreach ($runs as $run) {
            // Long past stale and still nowhere: stop burning worker time on it
            // and tell the visitor plainly, with Retry still available.
            if ($run->processing_started_at !== null && $run->processing_started_at->lt($abandon)) {
                $run->forceFill([
                    'status' => ToolRun::STATUS_FAILED,
                    'failed_stage' => $run->stage,
                    'failure_code' => 'timed_out',
                    'failure_message' => 'The run stopped responding and was closed by the sweeper.',
                ])->save();

                $failed++;

                continue;
            }

            RunToolStagesJob::dispatch($run->id)->onQueue(config('hiremium.queue.name', 'cv'));
            $resumed++;
        }

        $this->info("Free tools sweep: {$resumed} resumed, {$failed} abandoned.");

        return self::SUCCESS;
    }
}
