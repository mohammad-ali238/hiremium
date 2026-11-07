<?php

namespace App\Jobs\Tools;

use App\Models\ToolRun;
use App\Services\Tools\ToolRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The safety net behind the visitor's tab.
 *
 * Stages normally run in the open result page, one Livewire request each, so
 * the progress bar moves immediately. If the visitor closes the tab halfway,
 * this job finishes the run in the background and the report is waiting the
 * next time they open their link.
 *
 * It is dispatched with a delay on first open, so in the common case it wakes
 * up to find there is nothing left to do — the runner's event log tells it so,
 * and no stage is ever paid for twice.
 */
class RunToolStagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** No auto-retry: the runner keeps its own per-stage attempt count. */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $runId) {}

    public function handle(ToolRunner $runner): void
    {
        $run = ToolRun::find($this->runId);

        // Never start work the visitor has not asked for by opening their link.
        if ($run === null || $run->opened_at === null || $run->isFinished()) {
            return;
        }

        // One stage per pass, until the run finishes, a stage fails, or another
        // runner holds the lock (advance() returns false in all three cases).
        $guard = count($runner->stages($run)) + 1;

        while ($guard-- > 0 && $runner->advance($run)) {
            $run->refresh();
        }
    }
}
