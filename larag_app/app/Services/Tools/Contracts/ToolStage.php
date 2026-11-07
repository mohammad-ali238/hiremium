<?php

namespace App\Services\Tools\Contracts;

use App\Models\ToolRun;

/**
 * One step of a tool run. Stages are resumable: the runner executes them in
 * order, one per request, and a retry re-runs only the stage that failed.
 */
interface ToolStage
{
    /** Stable key stored on the run + its log events, e.g. 'parse'. */
    public function key(): string;

    /** Big user-facing progress label, e.g. 'Reading your resume'. */
    public function label(): string;

    /** Inline SVG path data for the step icon. */
    public function iconPath(): string;

    /** Relative weight of this stage in the progress bar. */
    public function weight(): int;

    /**
     * Do the work and persist it on the run, returning what the event log needs
     * (provider, model, tokens). Throwing marks the stage failed; the runner
     * records the event, the reason and offers a retry.
     */
    public function run(ToolRun $run): StageResult;
}
