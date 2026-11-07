<?php

namespace App\Services\Tools\Stages;

use App\Models\ToolRun;
use App\Services\Tools\Contracts\ToolStage;

/** Shared plumbing for the stages of a run: reading and growing its output. */
abstract class Stage implements ToolStage
{
    /** Every stage weighs the same unless it says otherwise. */
    public function weight(): int
    {
        return 1;
    }

    /** Merge new keys into the run's output and persist immediately: the next
     *  stage runs in a different request and reads what this one wrote. */
    protected function write(ToolRun $run, array $data): void
    {
        $run->output = array_merge((array) $run->output, $data);
        $run->save();
    }

    protected function read(ToolRun $run, string $key, mixed $default = null): mixed
    {
        return ((array) $run->output)[$key] ?? $default;
    }
}
