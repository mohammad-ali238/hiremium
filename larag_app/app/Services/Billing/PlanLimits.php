<?php

namespace App\Services\Billing;

use App\Models\Job;
use App\Models\Plan;
use App\Models\Workspace;

/**
 * Resolves a workspace's effective plan (the owner's active subscription plan,
 * or Free) and answers "can this workspace do X within its limits?" — one place
 * for every quota check across the dashboard.
 */
class PlanLimits
{
    public function planFor(Workspace $workspace): ?Plan
    {
        return $workspace->ownerSubscription()?->plan
            ?? Plan::where('name', 'Free')->first();
    }

    public function maxJobs(Workspace $workspace): int
    {
        return $this->limit($workspace, 'max_jobs', 3);
    }

    public function maxSeats(Workspace $workspace): int
    {
        return $this->limit($workspace, 'max_seats', 1);
    }

    public function maxFilesPerBatch(Workspace $workspace): int
    {
        return $this->limit($workspace, 'max_files_per_batch', (int) config('hiremium.ai.max_cvs_per_batch', 25));
    }

    public function maxFileMb(Workspace $workspace): int
    {
        return $this->limit($workspace, 'max_file_mb', (int) config('hiremium.uploads.max_file_mb', 10));
    }

    public function seatsUsed(Workspace $workspace): int
    {
        return $workspace->users()->count()
            + $workspace->invites()->whereNull('accepted_at')->count();
    }

    public function canAddSeat(Workspace $workspace): bool
    {
        return $this->seatsUsed($workspace) < $this->maxSeats($workspace);
    }

    public function jobsUsed(Workspace $workspace): int
    {
        // Jobs arrive in Phase 4; guard so this is safe to call now.
        return class_exists(Job::class)
            ? Job::where('workspace_id', $workspace->id)->count()
            : 0;
    }

    public function canAddJob(Workspace $workspace): bool
    {
        return $this->jobsUsed($workspace) < $this->maxJobs($workspace);
    }

    private function limit(Workspace $workspace, string $column, int $default): int
    {
        $plan = $this->planFor($workspace);

        return $plan && $plan->{$column} !== null ? (int) $plan->{$column} : $default;
    }
}
