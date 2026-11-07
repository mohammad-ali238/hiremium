<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'plan_id', 'starts_at', 'ends_at', 'credits_remaining', 'credits_total', 'credits_reset_at', 'status', 'billing_interval',
    'plan_name_snapshot', 'plan_price_snapshot', 'assign_source', 'assigned_by', 'cancelled_at', 'expired_at', 'renewal_grace_at',
    'lemonsqueezy_subscription_id', 'lemonsqueezy_order_id', 'lemonsqueezy_customer_id',
    'lemonsqueezy_status', 'renews_at',
])]
class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'credits_remaining' => 'integer',
            'credits_total' => 'integer',
            'plan_price_snapshot' => 'decimal:2',
            'credits_reset_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'renewal_grace_at' => 'datetime',
            'renews_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** The admin who assigned this plan, when it was assigned by hand. */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function planHistories(): HasMany
    {
        return $this->hasMany(UserPlanHistory::class);
    }

    /**
     * Usable right now: active, started, and not yet ended. A subscription the
     * admin dated into the future is NOT active until its start date.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    /** Assigned but not started yet — waiting for its start date. */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '>', now());
    }

    /**
     * Credits granted for the current period — this user's own stored total,
     * NOT the plan's current configuration. Older rows written before the
     * snapshot existed fall back to the plan so nothing reads as zero.
     */
    public function creditsTotal(): int
    {
        if ($this->credits_total !== null) {
            return (int) $this->credits_total;
        }

        return (int) ($this->plan?->creditsFor($this->billing_interval ?? Plan::INTERVAL_MONTHLY) ?? 0);
    }

    /** Credits already spent in the current period. */
    public function creditsUsed(): int
    {
        return max(0, $this->creditsTotal() - (int) $this->credits_remaining);
    }

    /** Plan name for display — the live plan, or the snapshot if the plan is gone. */
    public function planName(): string
    {
        return $this->plan?->name ?? ($this->plan_name_snapshot ?: 'No plan');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ! $this->starts_at->isFuture()
            && $this->ends_at->isFuture()
            && $this->credits_remaining > 0;
    }

    /** Running on borrowed time while we wait for the provider's renewal. */
    public function inRenewalGrace(): bool
    {
        return $this->renewal_grace_at !== null
            && $this->status === self::STATUS_ACTIVE
            && $this->ends_at->isFuture();
    }

    /** Assigned for a later date — not granting anything yet. */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->starts_at->isFuture();
    }
}
