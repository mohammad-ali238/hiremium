<?php

namespace App\Observers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UserPlanHistory;
use App\Services\Billing\PlanHistory;

/**
 * Keeps the plan history complete for changes made outside the provisioner —
 * above all a subscription deleted in the admin panel, which must still leave a
 * "plan deleted" entry behind, and a status edited by hand there.
 */
class SubscriptionObserver
{
    public function __construct(private readonly PlanHistory $history) {}

    /**
     * Snapshot the plan onto the row for subscriptions created outside the
     * provisioner (the admin Subscriptions resource, seeders), so every user
     * carries their own credit total instead of reading the plans table.
     */
    public function creating(Subscription $subscription): void
    {
        $plan = $subscription->plan ?? Plan::find($subscription->plan_id);

        if (! $plan) {
            return;
        }

        $interval = $subscription->billing_interval ?: Plan::INTERVAL_MONTHLY;

        if ($subscription->credits_total === null) {
            $subscription->credits_total = $plan->creditsFor($interval);
        }

        $subscription->plan_name_snapshot ??= $plan->name;
        $subscription->plan_price_snapshot ??= $plan->priceFor($interval) ?? 0;
    }

    /** A subscription added by hand in the admin panel still opens the history. */
    public function created(Subscription $subscription): void
    {
        if (PlanHistory::isSuppressed()) {
            return;
        }

        $this->history->record($subscription, UserPlanHistory::EVENT_ASSIGNED, UserPlanHistory::SOURCE_ADMIN);
    }

    /**
     * An admin editing the subscription directly (status dropdown in Filament)
     * is logged like any other transition.
     */
    public function updated(Subscription $subscription): void
    {
        if (PlanHistory::isSuppressed() || ! $subscription->wasChanged('status')) {
            return;
        }

        $event = match ($subscription->status) {
            Subscription::STATUS_CANCELLED => UserPlanHistory::EVENT_CANCELLED,
            Subscription::STATUS_EXPIRED => UserPlanHistory::EVENT_EXPIRED,
            Subscription::STATUS_SUPERSEDED => UserPlanHistory::EVENT_SUPERSEDED,
            Subscription::STATUS_ACTIVE => UserPlanHistory::EVENT_RESUMED,
            default => null,
        };

        if ($event === null) {
            return;
        }

        $this->history->recordOnce($subscription, $event, UserPlanHistory::SOURCE_ADMIN, null, [
            'cancelled_at' => $subscription->cancelled_at,
            'expired_at' => $subscription->expired_at,
        ]);
    }

    public function deleted(Subscription $subscription): void
    {
        $this->history->recordDeleted($subscription);
    }
}
