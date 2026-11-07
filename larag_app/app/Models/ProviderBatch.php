<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single async provider Batch API submission for a resume batch's overflow.
 */
class ProviderBatch extends Model
{
    use BelongsToWorkspace;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'resume_batch_id', 'workspace_id', 'provider', 'status',
        'provider_batch_id', 'input_file_id', 'output_file_id',
        'request_count', 'completed_count', 'failed_count', 'cost_per_item',
        'error', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'request_count' => 'integer',
            'completed_count' => 'integer',
            'failed_count' => 'integer',
            'cost_per_item' => 'integer',
            'meta' => 'array',
        ];
    }

    public function resumeBatch(): BelongsTo
    {
        return $this->belongsTo(ResumeBatch::class, 'resume_batch_id');
    }

    /** Still awaiting the provider (not yet terminal). */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_PROCESSING], true);
    }

    /**
     * URL where this batch's status can be checked. OpenAI has a console page;
     * Gemini only exposes the REST operation endpoint (requires the API key
     * as an x-goog-api-key header).
     */
    public function checkUrl(): ?string
    {
        if (! $this->provider_batch_id) {
            return null;
        }

        return match ($this->provider) {
            'openai' => 'https://platform.openai.com/batches/'.$this->provider_batch_id,
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta/'.ltrim($this->provider_batch_id, '/'),
            default => null,
        };
    }
}
