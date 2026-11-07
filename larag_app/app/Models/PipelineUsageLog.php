<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * One AI call made by the CV pipeline, linked to its resume batch and file.
 * Live lane logs a 'parse' and a 'match' row per CV; the async batch lane logs
 * a single 'combined' row.
 */
class PipelineUsageLog extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    public const OP_PARSE = 'parse';

    public const OP_MATCH = 'match';

    public const OP_COMBINED = 'combined';

    public const LANE_LIVE = 'live';

    public const LANE_BATCH = 'batch';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id', 'resume_batch_id', 'resume_file_id',
        'operation', 'lane', 'ai_provider', 'ai_model',
        'tokens_used', 'credits_charged', 'score', 'confidence', 'duration_ms',
        'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
            'credits_charged' => 'integer',
            'score' => 'integer',
            'confidence' => 'float',
            'duration_ms' => 'integer',
        ];
    }

    /** Write a log row without ever letting logging break the pipeline. */
    public static function record(array $attributes): void
    {
        try {
            static::create($attributes);
        } catch (Throwable) {
            // Usage logging is best-effort.
        }
    }

    public function resumeBatch(): BelongsTo
    {
        return $this->belongsTo(ResumeBatch::class, 'resume_batch_id');
    }

    public function resumeFile(): BelongsTo
    {
        return $this->belongsTo(ResumeFile::class, 'resume_file_id');
    }
}
