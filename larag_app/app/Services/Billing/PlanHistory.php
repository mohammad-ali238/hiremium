<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the user's plan lifecycle log (`user_plan_histories`). Every plan
 * change goes through here so the history is complete no matter who triggered
 * it: the admin "Assign plan" action, a LemonSqueezy webhook, the monthly reset
 * command, the /app free-plan guard or a deletion in the admin panel.
 */
class PlanHistory
{
    /** True while a caller writes its own history entries for a subscription. */
    private static bool $suppressed = false;

    /**
     * Run a block that records its own history entries: the Subscription
     * observer stays quiet for it, so a transition isn't logged twice (once by
     * the provisioner with full detail, once by the observer).
     */
    public static function withoutObserver(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    /** Whether the observer should skip logging right now. */
    public static function isSuppressed(): bool
    {
        return self::$suppressed;
    }

    /**
     * Record one lifecycle event. Plan name/credits/price are snapshotted from
     * the subscription (falling back to the plan) so the row survives edits.
     *
     * @param  array<string, mixed>  $overrides  Column overrides (e.g. cancelled_at, note).
     */
    public function record(
        Subscription $subscription,
        string $event,
        string $source,
        ?int $actorId = null,
        array $overrides = [],
    ): UserPlanHistory {
        $plan = $subscription->plan;

        return UserPlanHistory::create(array_merge([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->getKey(),
            'plan_id' => $subscription->plan_id,
            'plan_name' => $subscription->plan_name_snapshot ?: ($plan?->name ?? 'Plan'),
            'credits_total' => $subscription->creditsTotal(),
            'price' => $subscription->plan_price_snapshot ?? $plan?->priceFor($subscription->billing_interval ?? Plan::INTERVAL_MONTHLY) ?? 0,
            'billing_interval' => $subscription->billing_interval,
            'event' => $event,
            'source' => $source,
            'actor_id' => $actorId ?? Auth::id(),
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'assigned_at' => $subscription->starts_at,
            'created_at' => now(),
        ], $overrides));
    }

    /**
     * Record an event only when it isn't already the subscription's latest one.
     * Keeps the log clean where two paths report the same change (e.g. the user
     * cancels in the dashboard and the LemonSqueezy webhook echoes it back).
     */
    public function recordOnce(
        Subscription $subscription,
        string $event,
        string $source,
        ?int $actorId = null,
        array $overrides = [],
    ): ?UserPlanHistory {
        $latest = UserPlanHistory::query()
            ->where('subscription_id', $subscription->getKey())
            ->latest('id')
            ->value('event');

        if ($latest === $event) {
            return null;
        }

        return $this->record($subscription, $event, $source, $actorId, $overrides);
    }

    /**
     * Record the removal of a subscription row. Takes the values directly
     * because the row is (about to be) gone.
     */
    public function recordDeleted(Subscription $subscription, ?int $actorId = null): UserPlanHistory
    {
        return $this->record($subscription, UserPlanHistory::EVENT_DELETED, UserPlanHistory::SOURCE_ADMIN, $actorId, [
            // The row is going away, so don't point the history at a dead id.
            'subscription_id' => null,
            'note' => 'Subscription record deleted',
            'created_at' => now(),
        ]);
    }

    /** Convenience: the plan lifecycle events a user can see on their billing page. */
    public function forUser(User|int $user): Builder
    {
        return UserPlanHistory::query()
            ->where('user_id', $user instanceof User ? $user->getKey() : $user)
            ->latest('id');
    }
}
