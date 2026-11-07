<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'subscription_id', 'endpoint', 'lane', 'credits_consumed',
    'ai_provider', 'ai_model', 'tokens_used',
    'request_payload', 'response_payload', 'score',
    'status', 'error_message', 'ip_address',
])]
class UsageLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /** Answered inside the request (POST /v1/matching-score, /v1/parse-resume). */
    public const LANE_SYNC = 'sync';

    /** Queued through an ApiJob (POST /v1/*\/async) and answered by a worker. */
    public const LANE_ASYNC = 'async';

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'credits_consumed' => 'integer',
            'tokens_used' => 'integer',
            'score' => 'integer',
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
}
