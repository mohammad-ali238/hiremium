<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'credit_limit', 'yearly_credit_limit', 'price', 'yearly_price',
    'duration_days', 'is_active', 'is_public', 'description',
    'max_jobs', 'max_seats', 'max_files_per_batch', 'max_file_mb',
    'lemonsqueezy_variant_id', 'lemonsqueezy_yearly_variant_id',
])]
class Plan extends Model
{
    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_YEARLY = 'yearly';

    /** Days a yearly subscription's credit bundle stays valid. */
    public const YEARLY_DAYS = 365;

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'yearly_credit_limit' => 'integer',
            'price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'max_jobs' => 'integer',
            'max_seats' => 'integer',
            'max_files_per_batch' => 'integer',
            'max_file_mb' => 'integer',
        ];
    }

    /** A free plan carries no price — reset by cron, not by billing. */
    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Normalize an arbitrary value to a valid interval (defaults to monthly). */
    public static function normalizeInterval(?string $interval): string
    {
        return $interval === self::INTERVAL_YEARLY ? self::INTERVAL_YEARLY : self::INTERVAL_MONTHLY;
    }

    /** Whether a yearly option is offered and purchasable for this plan. */
    public function yearlyAvailable(): bool
    {
        return $this->yearly_price !== null && ! empty($this->lemonsqueezy_yearly_variant_id);
    }

    /** The LemonSqueezy variant id for the given interval. */
    public function variantFor(string $interval): ?string
    {
        return self::normalizeInterval($interval) === self::INTERVAL_YEARLY
            ? $this->lemonsqueezy_yearly_variant_id
            : $this->lemonsqueezy_variant_id;
    }

    /** Credits granted for one period of the given interval (yearly = annual bundle). */
    public function creditsFor(string $interval): int
    {
        if (self::normalizeInterval($interval) === self::INTERVAL_YEARLY) {
            return (int) ($this->yearly_credit_limit ?: $this->credit_limit * 12);
        }

        return (int) $this->credit_limit;
    }

    /** Price for the given interval (null when yearly is not offered). */
    public function priceFor(string $interval): ?float
    {
        if (self::normalizeInterval($interval) === self::INTERVAL_YEARLY) {
            return $this->yearly_price !== null ? (float) $this->yearly_price : null;
        }

        return (float) $this->price;
    }

    /** Period length in days for the given interval. */
    public function durationDaysFor(string $interval): int
    {
        return self::normalizeInterval($interval) === self::INTERVAL_YEARLY
            ? self::YEARLY_DAYS
            : (int) $this->duration_days;
    }

    /**
     * Percentage saved by paying yearly vs 12× the monthly price (or null when
     * there's no yearly price or no positive saving).
     */
    public function yearlySavingsPercent(): ?int
    {
        if ($this->yearly_price === null || (float) $this->price <= 0) {
            return null;
        }

        $fullYear = (float) $this->price * 12;
        if ($fullYear <= 0) {
            return null;
        }

        $pct = (int) round((1 - ((float) $this->yearly_price / $fullYear)) * 100);

        return $pct > 0 ? $pct : null;
    }

    /**
     * Resolve a plan + interval from a LemonSqueezy variant id.
     *
     * @return array{plan: Plan, interval: string}|null
     */
    public static function resolveByVariant(string $variantId): ?array
    {
        if ($variantId === '') {
            return null;
        }

        $plan = self::query()->where('lemonsqueezy_variant_id', $variantId)->first();
        if ($plan) {
            return ['plan' => $plan, 'interval' => self::INTERVAL_MONTHLY];
        }

        $plan = self::query()->where('lemonsqueezy_yearly_variant_id', $variantId)->first();
        if ($plan) {
            return ['plan' => $plan, 'interval' => self::INTERVAL_YEARLY];
        }

        return null;
    }
}
