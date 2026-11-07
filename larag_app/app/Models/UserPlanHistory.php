<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a user's plan lifecycle log. Plan name, credit total and price
 * are snapshots taken when the entry was written, so the row still reads
 * correctly after the plan is edited or the subscription row is deleted.
 */
#[Fillable([
    'user_id', 'subscription_id', 'plan_id', 'plan_name', 'credits_total', 'price',
    'billing_interval', 'event', 'source', 'actor_id', 'note',
    'starts_at', 'ends_at', 'assigned_at', 'cancelled_at', 'expired_at',
])]
class UserPlanHistory extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_ASSIGNED = 'assigned';

    public const EVENT_RENEWED = 'renewed';

    public const EVENT_SUPERSEDED = 'superseded';

    public const EVENT_CANCELLED = 'cancelled';

    public const EVENT_RESUMED = 'resumed';

    public const EVENT_GRACE = 'grace';

    public const EVENT_EXPIRED = 'expired';

    public const EVENT_DELETED = 'deleted';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_AUTO_FREE = 'auto_free';

    public const SOURCE_CRON = 'cron';

    public const SOURCE_SELF = 'self';

    public const SOURCE_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'credits_total' => 'integer',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'assigned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'created_at' => 'datetime',
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

    /** The admin (or user) who triggered the event, when there was one. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            self::EVENT_ASSIGNED => 'Assigned',
            self::EVENT_RENEWED => 'Renewed',
            self::EVENT_SUPERSEDED => 'Replaced',
            self::EVENT_CANCELLED => 'Cancelled',
            self::EVENT_RESUMED => 'Resumed',
            self::EVENT_GRACE => 'Renewal grace',
            self::EVENT_EXPIRED => 'Expired',
            self::EVENT_DELETED => 'Deleted',
            default => ucfirst((string) $this->event),
        };
    }

    /** Badge colour for the event (matches <x-ui.badge> / Filament colours). */
    public function eventColor(): string
    {
        return match ($this->event) {
            self::EVENT_ASSIGNED, self::EVENT_RESUMED => 'success',
            self::EVENT_RENEWED => 'primary',
            self::EVENT_SUPERSEDED, self::EVENT_GRACE => 'warning',
            self::EVENT_CANCELLED, self::EVENT_EXPIRED, self::EVENT_DELETED => 'danger',
            default => 'gray',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_ADMIN => 'Assigned by admin',
            self::SOURCE_PAYMENT => 'Payment',
            self::SOURCE_AUTO_FREE => 'Auto (free plan)',
            self::SOURCE_CRON => 'Scheduled job',
            self::SOURCE_SELF => 'User action',
            default => 'System',
        };
    }
}
