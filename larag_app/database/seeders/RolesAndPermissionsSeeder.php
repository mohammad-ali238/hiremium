<?php

namespace Database\Seeders;

use App\Support\Rbac;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the granular permissions and the global role presets. Roles are global
 * (team_id null); assignment to users is workspace-scoped at runtime. Idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Global (team-independent) definitions.
        $registrar->setPermissionsTeamId(null);

        foreach (Rbac::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Rbac::ROLES as $role => $permissions) {
            $model = Role::findOrCreate($role, 'web');
            $model->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }
}
