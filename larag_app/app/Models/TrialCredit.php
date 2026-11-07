<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A free-trial AI credit grant, keyed on a unique email address. Its `api_key`
 * is used by the WordPress plugin/addons as the Bearer credential on the AI
 * endpoints; `uuid` identifies the grant for status polling and verification.
 */
#[Fillable([
    'email', 'email_canonical', 'uuid', 'api_key', 'site_url', 'site_domain', 'ip', 'reported_ip',
    'status', 'credits_total', 'credits_used', 'redirect_token', 'verified_at', 'expires_at',
])]
class TrialCredit extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    /** Default trial allowance (credits) and duration (days). */
    public const DEFAULT_CREDITS = 50;

    public const DEFAULT_DAYS = 30;

    protected function casts(): array
    {
        return [
            'credits_total' => 'integer',
            'credits_used' => 'integer',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(TrialUsageLog::class);
    }

    /**
     * Credits still available (never negative).
     */
    public function getCreditsRemainingAttribute(): int
    {
        return max(0, (int) $this->credits_total - (int) $this->credits_used);
    }

    /**
     * Whether this grant can currently authorise an AI call.
     */
    public function isUsable(): bool
    {
        return $this->status === self::STATUS_VERIFIED
            && $this->verified_at !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->credits_remaining > 0;
    }

    /**
     * Whether the grant is verified and not past its expiry (may be out of credits).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_VERIFIED
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Look up a grant by its plaintext api_key.
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        if ($apiKey === '') {
            return null;
        }

        return static::query()->where('api_key', $apiKey)->first();
    }
}
