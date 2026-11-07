<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger of LemonSqueezy payment/subscription webhook events —
 * an audit trail for money movement and the idempotency guard against
 * duplicate webhook deliveries. Created-only (no updates).
 */
#[Fillable([
    'user_id', 'subscription_id', 'plan_id',
    'lemonsqueezy_subscription_id', 'lemonsqueezy_order_id', 'lemonsqueezy_customer_id',
    'event_name', 'status', 'amount_total', 'currency', 'cancelled_at', 'payload',
])]
class PaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount_total' => 'integer',
            'cancelled_at' => 'datetime',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
