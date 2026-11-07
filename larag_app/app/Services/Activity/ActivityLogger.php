<?php

namespace App\Services\Activity;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Single entry point for writing workspace activity. Every meaningful action
 * should call this so the dashboard feed + Activity Log stay complete.
 *
 * Usage: ActivityLogger::log('candidate.shortlisted', $candidate, 'Shortlisted Ayesha Khan');
 */
class ActivityLogger
{
    public static function log(string $action, ?Model $subject = null, ?string $description = null, array $properties = []): ?Activity
    {
        $user = Auth::user();
        $workspaceId = $user?->workspace_id;

        // Activity is always workspace-scoped; skip if there is no workspace
        // context (e.g. an unauthenticated/system path without one).
        if (! $workspaceId) {
            return null;
        }

        return Activity::create([
            'workspace_id' => $workspaceId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties !== [] ? $properties : null,
            'created_at' => now(),
        ]);
    }
}
