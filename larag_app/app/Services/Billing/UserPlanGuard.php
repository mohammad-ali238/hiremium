<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanHistory;
use Illuminate\Support\Facades\Cache;

/**
 * Guarantees the account holder always has a plan. Called on every /app page
 * (via EnsureUserPlan) and nightly by hiremium:reset-monthly-credits: if the
 * user has no usable subscription — because the previous one was cancelled,
 * expired, deleted, or they never had one — the Free plan is assigned and the
 * assignment is logged to the plan history.
 *
 * Lapsed rows (status still `active` but past `ends_at`) are marked expired
 * first, so the history records the expiry before the Free plan takes over.
 * A plan the admin dated into the future is only scheduled; it takes over on its
 * start date (activateDue), superseding whatever covered the gap.
 *
 * A PAID plan that lapsed while the provider is still renewing it (or retrying a
 * card) is never downgraded to Free: it is given a grace extension instead, so a
 * paying customer keeps their plan and its limits while the payment resolves.
 *
 * The Free plan runs for its own `duration_days` period and is re-assigned when
 * that period ends — each cycle leaving an `expired` + `assigned` pair in the
 * history. Running out of credits mid-period never triggers this: only the
 * expiry date does.
 */
class UserPlanGuard
{
    public function __construct(
        private SubscriptionProvisioner $provisioner,
        private PlanHistory $history,
    ) {}

    /**
     * @return Subscription|null The usable subscription, or null when no Free
     *                           plan exists / the user isn't the account holder.
     */
    public function ensure(User $user): ?Subscription
    {
        // Always record a finished period as expired, even when a newer plan has
        // already taken over — a row left flagged `active` past its end date is
        // misleading in the admin and leaves a hole in the history.
        $expired = $this->expireLapsed($user);

        if ($active = $this->activeFor($user)) {
            // A plan scheduled for a later date may have just come due, in which
            // case it supersedes whatever was covering the gap.
            return $this->provisioner->activateDue($user) ?: $active;
        }

        // Credits are drawn from the workspace OWNER's subscription, so only the
        // account holder gets a FIRST plan; invited members spend the owner's.
        // Someone who already held a plan of their own keeps being renewed
        // either way — their period simply rolled over.
        if (! $expired && ! $this->isAccountHolder($user) && ! $this->hadOwnPlan($user)) {
            return null;
        }

        return $this->assignFreePlan($user);
    }

    /**
     * Assign the Free plan. Taken under a short lock and re-checked inside it,
     * so two concurrent requests (a page load racing a Livewire poll) can't
     * each create a plan and leave the first one instantly superseded.
     */
    public function assignFreePlan(User $user, string $source = UserPlanHistory::SOURCE_AUTO_FREE): ?Subscription
    {
        $lock = Cache::lock('user-plan-guard:'.$user->getKey(), 10);

        if (! $lock->get()) {
            // Another request is assigning right now — use whatever it created.
            return $this->activeFor($user);
        }

        try {
            return $this->activeFor($user) ?: $this->assign($user, $source);
        } finally {
            $lock->release();
        }
    }

    private function assign(User $user, string $source): ?Subscription
    {
        $free = $this->freePlan();

        if (! $free) {
            return null;
        }

        return $this->provisioner->provision(
            $user,
            $free,
            [],
            now(),
            // A real 30-day (plan duration) period: when it ends the plan is
            // re-assigned, which is what refreshes the credits each month.
            now()->addDays($free->durationDaysFor(Plan::INTERVAL_MONTHLY)),
            $source,
        );
    }

    /** The user's currently usable subscription, if any. */
    private function activeFor(User $user): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->latest('id')
            ->first();
    }

    /** The plan handed out for free — by name first, then any free active plan. */
    public function freePlan(): ?Plan
    {
        return Plan::query()->where('name', 'Free')->first()
            ?? Plan::query()->where('is_active', true)->where('price', '<=', 0)->orderBy('id')->first();
    }

    /**
     * Rows still flagged active whose period has run out: `scopeActive` already
     * ignores them, this turns the lapse into a recorded expiry — unless the
     * provider is still working on a renewal, in which case the plan is extended
     * instead (see settleLapsed).
     *
     * @return bool Whether anything was expired.
     */
    private function expireLapsed(User $user): bool
    {
        $lapsed = Subscription::query()
            ->where('user_id', $user->getKey())
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->with('plan')
            ->get();

        $expired = false;

        foreach ($lapsed as $subscription) {
            $expired = $this->settleLapsed($subscription) || $expired;
        }

        return $expired;
    }

    /**
     * Decide what a lapsed subscription becomes.
     *
     * A paid subscription the provider still considers live (renewing, on trial,
     * or a card being retried during dunning) is NOT downgraded: its period is
     * extended once by the renewal grace window, so the customer keeps their
     * plan — and its limits — while the payment resolves. The renewal webhook
     * then moves the period forward properly; if nothing arrives before the
     * grace runs out, it expires and Free takes over as usual.
     *
     * @return bool Whether the subscription was expired (false = given grace).
     */
    public function settleLapsed(Subscription $subscription): bool
    {
        $graceDays = (int) config('hiremium.billing.renewal_grace_days', 5);

        if ($graceDays > 0 && $this->awaitingProviderRenewal($subscription)) {
            $extendedTo = $subscription->ends_at->copy()->addDays($graceDays);

            $subscription->update([
                'renewal_grace_at' => now(),
                'ends_at' => $extendedTo,
            ]);

            $this->history->record($subscription, UserPlanHistory::EVENT_GRACE, UserPlanHistory::SOURCE_PAYMENT, null, [
                'note' => 'Waiting for the renewal payment — plan kept until '.$extendedTo->toFormattedDayDateString(),
            ]);

            return false;
        }

        $this->provisioner->expire($subscription);

        return true;
    }

    /**
     * The provider has not finished with this subscription: it is LemonSqueezy
     * backed, its last known provider status is a live one, and it has not been
     * given its one grace extension yet. A subscription the customer cancelled
     * is excluded — that one is meant to end at the period end.
     */
    private function awaitingProviderRenewal(Subscription $subscription): bool
    {
        return $subscription->renewal_grace_at === null
            && ! empty($subscription->lemonsqueezy_subscription_id)
            && in_array($subscription->lemonsqueezy_status, ['active', 'on_trial', 'past_due'], true);
    }

    /** Whether this user has ever held a subscription of their own. */
    private function hadOwnPlan(User $user): bool
    {
        return Subscription::query()->where('user_id', $user->getKey())->exists();
    }

    /** Owner of their workspace (or an account with no workspace yet). */
    private function isAccountHolder(User $user): bool
    {
        if ($user->ownedWorkspaces()->exists()) {
            return true;
        }

        return $user->workspace_id === null;
    }
}
