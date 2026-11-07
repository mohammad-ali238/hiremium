<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes a model to a workspace. Auto-stamps workspace_id on create from the
 * authenticated user, and provides query scopes. NOTE: no global scope — queue
 * jobs run without an auth context, so callers scope explicitly via
 * forCurrentWorkspace()/forWorkspace() on the web side.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::creating(function ($model) {
            if (empty($model->workspace_id) && Auth::check() && Auth::user()->workspace_id) {
                $model->workspace_id = Auth::user()->workspace_id;
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForCurrentWorkspace(Builder $query): Builder
    {
        return $query->where('workspace_id', Auth::user()?->workspace_id);
    }
}
