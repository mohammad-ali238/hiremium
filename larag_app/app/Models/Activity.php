<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single workspace activity entry (append-only). Written via ActivityLogger;
 * surfaced as the dashboard "recent 5" and the full Activity Log page.
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['workspace_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
