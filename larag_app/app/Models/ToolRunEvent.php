<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable per-stage log line for a Free Tool run (table `aitools_run_events`):
 * which provider/model actually ran, tokens, duration, failure detail.
 */
class ToolRunEvent extends Model
{
    protected $table = 'aitools_run_events';

    public const UPDATED_AT = null;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'provider_inherited' => 'boolean',
            'attempt' => 'integer',
            'tokens_prompt' => 'integer',
            'tokens_completion' => 'integer',
            'tokens_total' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class, 'aitools_run_id');
    }
}
