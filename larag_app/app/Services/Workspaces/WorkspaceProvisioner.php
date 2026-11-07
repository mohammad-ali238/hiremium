<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\UserPlanGuard;
use App\Support\Rbac;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates workspaces and attaches users with a role. Roles/permissions live in
 * spatie's tables; assignment is scoped to the workspace (spatie "teams" mode),
 * so we set the permissions team id before assigning.
 */
class WorkspaceProvisioner
{
    /** Create a brand-new workspace owned by $user and give them the Owner role. */
    public function createOwnerWorkspace(User $user, ?string $name = null): Workspace
    {
        $workspace = Workspace::create([
            'name' => $name ?: $this->defaultName($user),
            'owner_id' => $user->id,
        ]);

        $user->forceFill(['workspace_id' => $workspace->id])->save();

        $this->ensureRole('Owner');
        $this->inTeam($workspace->id, fn () => $user->syncRoles(['Owner']));

        $this->ensureBilling($workspace);

        return $workspace;
    }

    /**
     * Ensure a workspace has a credit wallet and its owner an active
     * subscription (a Free one when they have none). Idempotent.
     */
    public function ensureBilling(Workspace $workspace): void
    {
        $workspace->creditWallet()->firstOrCreate([], ['pack_balance' => 0]);

        $owner = $workspace->owner;
        if (! $owner || $owner->subscriptions()->active()->exists()) {
            return;
        }

        // Assigning the Free plan (and logging it to the plan history) is the
        // guard's job — same path as the /app check, so history stays complete.
        app(UserPlanGuard::class)->assignFreePlan($owner);
    }

    /** Attach an existing/new user to a workspace with a given role. */
    public function attachMember(User $user, Workspace $workspace, string $role): void
    {
        $user->forceFill(['workspace_id' => $workspace->id])->save();

        $this->ensureRole($role);
        $this->inTeam($workspace->id, fn () => $user->syncRoles([$role]));
    }

    /**
     * Ensure a global (team-independent) role exists — with its permissions —
     * before assigning it, so provisioning yields a working account even if the
     * roles seeder never ran (tests, or a fresh install / legacy user). The
     * permission sync only runs when the role has none yet, so a properly
     * seeded role is never touched.
     */
    private function ensureRole(string $role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null); // roles/permissions are global

        try {
            foreach (Rbac::PERMISSIONS as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $model = Role::findOrCreate($role, 'web');

            if (isset(Rbac::ROLES[$role]) && $model->permissions()->count() === 0) {
                $model->syncPermissions(Rbac::ROLES[$role]);
            }

            $registrar->forgetCachedPermissions();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    private function defaultName(User $user): string
    {
        $base = trim((string) $user->name) !== '' ? $user->name : 'My';

        return $base."'s Workspace";
    }

    /** Run a callback with the spatie permissions team id set to $workspaceId. */
    private function inTeam(int $workspaceId, callable $callback): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($workspaceId);

        try {
            $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
