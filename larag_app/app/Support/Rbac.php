<?php

namespace App\Support;

/**
 * Central definition of roles + granular permissions. Roles are presets over
 * the permission set; the Owner can fine-tune per member (direct permissions).
 * Seeded by RolesAndPermissionsSeeder; used by the team UI to render options.
 */
class Rbac
{
    /** Granular per-section permissions. */
    public const PERMISSIONS = [
        'jobs.view', 'jobs.manage',
        'candidates.view', 'candidates.manage',
        'uploads.manage',
        'reports.view',
        'team.manage',
        'billing.manage',
        'settings.manage',
        'activity.view',
    ];

    /** Role → permission presets. */
    public const ROLES = [
        'Owner' => self::PERMISSIONS,
        'Admin' => [
            'jobs.view', 'jobs.manage',
            'candidates.view', 'candidates.manage',
            'uploads.manage',
            'reports.view',
            'team.manage',
            'settings.manage',
            'activity.view',
        ],
        'Recruiter' => [
            'jobs.view', 'jobs.manage',
            'candidates.view', 'candidates.manage',
            'uploads.manage',
            'reports.view',
            'activity.view',
        ],
        'Viewer' => [
            'jobs.view', 'candidates.view', 'reports.view', 'activity.view',
        ],
    ];

    /** Roles an owner/admin may assign to a member (Owner is not assignable). */
    public static function assignableRoles(): array
    {
        return ['Admin', 'Recruiter', 'Viewer'];
    }
}
