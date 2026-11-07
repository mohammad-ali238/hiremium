<?php

namespace App\Livewire\Concerns;

use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceProvisioner;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared helper for workspace-scoped Livewire pages. Guarantees the user has a
 * workspace (self-heals legacy accounts created before provisioning), sets the
 * spatie permissions team id, and exposes the current workspace.
 */
trait InteractsWithWorkspace
{
    public function bootInteractsWithWorkspace(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        // Self-heal: any authenticated user without a workspace (legacy/seeded
        // accounts, or provisioning that never ran) gets one on first access.
        if (! $user->workspace_id) {
            $workspace = app(WorkspaceProvisioner::class)->createOwnerWorkspace($user);
            $user->setRelation('workspace', $workspace);
        }

        if ($user->workspace_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->workspace_id);
        }
    }

    protected function currentWorkspace(): ?Workspace
    {
        return Auth::user()?->workspace;
    }
}
