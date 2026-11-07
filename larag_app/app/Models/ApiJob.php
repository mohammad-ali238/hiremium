<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

#[Fillable([
    'id', 'user_id', 'subscription_id', 'endpoint', 'status',
    'credits_charged', 'credits_refunded', 'input_meta', 'result',
    'error_code', 'error_message',
    'queued_at', 'started_at', 'finished_at',
])]
class ApiJob extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'input_meta' => 'array',
            'result' => 'array',
            'credits_charged' => 'integer',
            'credits_refunded' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $result): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(Throwable $e, int $creditsRefunded = 0): void
    {
        $code = 'error';
        if (property_exists($e, 'errorCode') && is_string($e->errorCode)) {
            $code = $e->errorCode;
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_code' => $code,
            'error_message' => $e->getMessage(),
            'credits_refunded' => $creditsRefunded,
            'finished_at' => now(),
        ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }
}
