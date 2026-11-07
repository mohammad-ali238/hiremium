<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

/**
 * Jobs are workspace-scoped: a user may only touch jobs in their own workspace,
 * and only with the matching spatie permission (evaluated within the workspace
 * team context set by the SetPermissionsTeam middleware).
 *
 * Platform admins (is_admin) bypass these checks so the Filament admin panel
 * can manage every workspace's jobs — consistent with the other admin
 * resources, which have no workspace policy at all.
 */
class JobPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('jobs.view');
    }

    public function view(User $user, Job $job): bool
    {
        return $job->workspace_id === $user->workspace_id && $user->can('jobs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('jobs.manage');
    }

    public function update(User $user, Job $job): bool
    {
        return $job->workspace_id === $user->workspace_id && $user->can('jobs.manage');
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->update($user, $job);
    }
}
