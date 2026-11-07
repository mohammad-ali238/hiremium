<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per free-trial AI call (kept separate from UsageLog, which records
 * paid-subscription usage).
 */
#[Fillable([
    'trial_credit_id', 'endpoint', 'credits_consumed',
    'ai_provider', 'ai_model', 'tokens_used',
    'request_payload', 'response_payload', 'score',
    'status', 'error_message', 'ip_address',
])]
class TrialUsageLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

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

    public function trialCredit(): BelongsTo
    {
        return $this->belongsTo(TrialCredit::class);
    }
}
