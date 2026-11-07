<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Single source of truth for turning a Plan into an active credit Subscription.
 *
 * Used by the Filament admin "Assign plan" action, the /app free-plan guard and
 * the LemonSqueezy webhook. The credit-lifecycle contract: a new subscription
 * supersedes any existing active rows for the user (cancelling them, history
 * kept), then a fresh row is created with credits = the plan's allotment for
 * the interval, SNAPSHOTTED onto the subscription as `credits_total`, and
 * ends_at = starts_at + plan duration. Every transition writes a
 * `user_plan_histories` entry via PlanHistory.
 */
class SubscriptionProvisioner
{
    public function __construct(private PlanHistory $history) {}

    /**
     * Provision a brand-new active subscription for a user (supersede-then-create).
     *
     * @param  array{lemonsqueezy_subscription_id?: string, lemonsqueezy_order_id?: string, lemonsqueezy_customer_id?: string, lemonsqueezy_status?: string, renews_at?: \DateTimeInterface|string|null}  $ls
     * @param  string  $source  How the plan was assigned (admin | payment | auto_free | cron | system).
     */
    public function provision(
        User $user,
        Plan $plan,
        array $ls = [],
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
        string $source = UserPlanHistory::SOURCE_SYSTEM,
        ?int $actorId = null,
    ): Subscription {
        return PlanHistory::withoutObserver(fn () => DB::transaction(function () use ($user, $plan, $ls, $startsAt, $endsAt, $source, $actorId) {
            $startsAt = $startsAt ?: now();
            $interval = Plan::normalizeInterval($ls['interval'] ?? Plan::INTERVAL_MONTHLY);

            $renewsAt = ! empty($ls['renews_at']) ? Carbon::parse($ls['renews_at']) : null;
            $endsAt = $endsAt
                ?: ($renewsAt ?: Carbon::parse($startsAt)->addDays($plan->durationDaysFor($interval)));

            // The previous plan is cancelled (superseded) — history kept. A plan
            // dated into the FUTURE is only scheduled: the user keeps their
            // current plan until the start date, where activateDue() takes over.
            if (! Carbon::parse($startsAt)->isFuture()) {
                $this->supersedeActiveFor($user->getKey(), $source, $actorId, 'Replaced by '.$plan->name);
            }

            $subscription = Subscription::create([
                'user_id' => $user->getKey(),
                'plan_id' => $plan->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'credits_remaining' => $plan->creditsFor($interval),
                // The user's own stored total — plan edits never rewrite it.
                'credits_total' => $plan->creditsFor($interval),
                'plan_name_snapshot' => $plan->name,
                'plan_price_snapshot' => $plan->priceFor($interval) ?? 0,
                'assign_source' => $source,
                'assigned_by' => $actorId,
                // Nothing refills a subscription in place: a free plan's period
                // rolls over into a NEW subscription (hiremium:reset-monthly-credits
                // / the /app guard) and a paid one is refreshed by the billing renewal.
                'credits_reset_at' => null,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_interval' => $interval,
                'lemonsqueezy_subscription_id' => $ls['lemonsqueezy_subscription_id'] ?? null,
                'lemonsqueezy_order_id' => $ls['lemonsqueezy_order_id'] ?? null,
                'lemonsqueezy_customer_id' => $ls['lemonsqueezy_customer_id'] ?? null,
                'lemonsqueezy_status' => $ls['lemonsqueezy_status'] ?? null,
                'renews_at' => $renewsAt,
            ]);

            $this->history->record($subscription, UserPlanHistory::EVENT_ASSIGNED, $source, $actorId);

            return $subscription;
        }));
    }

    /**
     * Idempotently reconcile a local subscription against a LemonSqueezy
     * subscription's current state. Used for BOTH renewals and upgrades:
     *
     *  - upgrade   → the incoming variant maps to a different Plan than ours.
     *  - renewal   → same plan, but `renews_at` has advanced.
     *
     * In either case we reset credits to the (new) plan's limit, re-snapshot the
     * total and move the period forward. When neither changed (a
     * duplicate/first-payment echo), only the LS status is synced — no credit
     * top-up.
     *
     * @return Subscription|null Null when no matching local subscription exists.
     */
    public function syncFromLemonSqueezy(string $lsSubId, Plan $plan, string $interval, Carbon $renewsAt, string $status): ?Subscription
    {
        return PlanHistory::withoutObserver(fn () => DB::transaction(function () use ($lsSubId, $plan, $interval, $renewsAt, $status) {
            $interval = Plan::normalizeInterval($interval);

            $subscription = Subscription::query()
                ->where('lemonsqueezy_subscription_id', $lsSubId)
                ->with('plan')
                ->latest('id')
                ->first();

            if (! $subscription) {
                return null;
            }

            $planChanged = (int) $subscription->plan_id !== (int) $plan->getKey();
            $intervalChanged = $subscription->billing_interval !== $interval;
            $renewed = $subscription->renews_at === null
                || $renewsAt->greaterThan($subscription->renews_at);
            // The renewal webhook can arrive after the period already lapsed, by
            // which time the /app guard has expired this row and put the user on
            // Free as a stop-gap. The provider is authoritative: bring the paid
            // subscription back and retire the stop-gap below.
            $reactivated = $subscription->status !== Subscription::STATUS_ACTIVE
                && in_array($status, ['active', 'on_trial'], true);

            if ($planChanged || $intervalChanged || $renewed || $reactivated) {
                // A plan/interval change replaces the old plan in place: log the
                // old one as superseded before overwriting the snapshot.
                if ($planChanged || $intervalChanged) {
                    $this->history->record($subscription, UserPlanHistory::EVENT_SUPERSEDED, UserPlanHistory::SOURCE_PAYMENT, null, [
                        'note' => 'Changed to '.$plan->name,
                    ]);
                }

                $subscription->update([
                    'plan_id' => $plan->getKey(),
                    'credits_remaining' => $plan->creditsFor($interval),
                    'credits_total' => $plan->creditsFor($interval),
                    'plan_name_snapshot' => $plan->name,
                    'plan_price_snapshot' => $plan->priceFor($interval) ?? 0,
                    'ends_at' => $renewsAt,
                    'renews_at' => $renewsAt,
                    'status' => Subscription::STATUS_ACTIVE,
                    'billing_interval' => $interval,
                    'lemonsqueezy_status' => $status,
                    'cancelled_at' => null,
                    'expired_at' => null,
                    // The wait is over — the next period can earn its own grace.
                    'renewal_grace_at' => null,
                ]);

                $subscription->refresh()->load('plan');

                $event = match (true) {
                    $planChanged || $intervalChanged => UserPlanHistory::EVENT_ASSIGNED,
                    $renewed => UserPlanHistory::EVENT_RENEWED,
                    default => UserPlanHistory::EVENT_RESUMED,
                };

                $this->history->record($subscription, $event, UserPlanHistory::SOURCE_PAYMENT);

                // A user must never hold two active subscriptions — the paid one
                // the provider just confirmed wins over anything else.
                $this->supersedeOtherActive($subscription, UserPlanHistory::SOURCE_PAYMENT, null, 'Replaced by '.$plan->name);
            } else {
                $subscription->update(['lemonsqueezy_status' => $status]);
            }

            return $subscription->refresh();
        }));
    }

    /**
     * Revoke access for a LemonSqueezy subscription that has expired.
     */
    public function revoke(string $lsSubId): void
    {
        PlanHistory::withoutObserver(function () use ($lsSubId): void {
            $subscriptions = Subscription::query()
                ->where('lemonsqueezy_subscription_id', $lsSubId)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->with('plan')
                ->get();

            foreach ($subscriptions as $subscription) {
                $subscription->update([
                    'status' => Subscription::STATUS_EXPIRED,
                    'expired_at' => now(),
                    'lemonsqueezy_status' => 'expired',
                ]);

                $this->history->recordOnce($subscription, UserPlanHistory::EVENT_EXPIRED, UserPlanHistory::SOURCE_PAYMENT, null, [
                    'expired_at' => $subscription->expired_at,
                ]);
            }
        });
    }

    /**
     * Record only the LemonSqueezy status on a local subscription (e.g. on
     * cancellation, where access continues until the period ends).
     */
    public function markStatus(string $lsSubId, string $status): void
    {
        Subscription::query()
            ->where('lemonsqueezy_subscription_id', $lsSubId)
            ->update(['lemonsqueezy_status' => $status]);
    }

    /**
     * Handle a cancellation: the subscription stays active until the period end
     * (no more renewals), so we keep access but align `ends_at` with the exact
     * end date LemonSqueezy provides — keeping both sides identical. The
     * cancellation is logged once, whichever side reports it first (the user's
     * own cancel in the dashboard or the webhook echoing it back).
     */
    public function markCancelled(string $lsSubId, string $status, ?Carbon $endsAt = null, string $source = UserPlanHistory::SOURCE_PAYMENT, ?int $actorId = null): void
    {
        PlanHistory::withoutObserver(function () use ($lsSubId, $status, $endsAt, $source, $actorId): void {
            $update = ['lemonsqueezy_status' => $status, 'cancelled_at' => now()];

            if ($endsAt) {
                $update['ends_at'] = $endsAt;
            }

            $subscriptions = Subscription::query()
                ->where('lemonsqueezy_subscription_id', $lsSubId)
                ->with('plan')
                ->get();

            foreach ($subscriptions as $subscription) {
                $subscription->update($update);

                $this->history->recordOnce($subscription, UserPlanHistory::EVENT_CANCELLED, $source, $actorId, [
                    'cancelled_at' => $subscription->cancelled_at,
                    'note' => 'Access kept until '.($subscription->ends_at?->toFormattedDayDateString() ?? 'period end'),
                ]);
            }
        });
    }

    /**
     * Handle a resume (un-cancel): the subscription will renew again, so restore
     * the LemonSqueezy status and align `renews_at` with the date LemonSqueezy
     * returns — keeping both sides identical.
     */
    public function markResumed(string $lsSubId, string $status, ?Carbon $renewsAt = null, string $source = UserPlanHistory::SOURCE_PAYMENT, ?int $actorId = null): void
    {
        PlanHistory::withoutObserver(function () use ($lsSubId, $status, $renewsAt, $source, $actorId): void {
            $update = ['lemonsqueezy_status' => $status, 'cancelled_at' => null];

            if ($renewsAt) {
                $update['renews_at'] = $renewsAt;
            }

            $subscriptions = Subscription::query()
                ->where('lemonsqueezy_subscription_id', $lsSubId)
                ->with('plan')
                ->get();

            foreach ($subscriptions as $subscription) {
                $subscription->update($update);

                $this->history->recordOnce($subscription, UserPlanHistory::EVENT_RESUMED, $source, $actorId);
            }
        });
    }

    /**
     * A scheduled plan that has reached its start date becomes the user's only
     * usable subscription: whatever they were on in the meantime (their old
     * plan, or a Free stop-gap) is superseded. Also repairs any other case of
     * two usable rows — the one that started most recently wins.
     *
     * @return Subscription|null The surviving subscription, when there was one.
     */
    public function activateDue(User $user, string $source = UserPlanHistory::SOURCE_SYSTEM): ?Subscription
    {
        return PlanHistory::withoutObserver(function () use ($user, $source): ?Subscription {
            $usable = Subscription::query()
                ->where('user_id', $user->getKey())
                ->active()
                ->with('plan')
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get();

            if ($usable->count() < 2) {
                return $usable->first();
            }

            $winner = $usable->shift();

            foreach ($usable as $old) {
                $old->update([
                    'status' => Subscription::STATUS_SUPERSEDED,
                    'cancelled_at' => $old->cancelled_at ?: now(),
                ]);

                $this->history->record($old, UserPlanHistory::EVENT_SUPERSEDED, $source, null, [
                    'cancelled_at' => $old->cancelled_at,
                    'note' => 'Replaced by '.($winner->plan?->name ?? $winner->plan_name_snapshot),
                ]);
            }

            return $winner;
        });
    }

    /**
     * Cancel a plan from the admin panel. A LemonSqueezy-backed subscription is
     * cancelled AT the provider first — otherwise the next webhook would simply
     * reactivate it — and keeps access until the period end, exactly like a
     * self-serve cancellation. A plan the admin assigned by hand has no provider
     * to tell, so it ends immediately.
     *
     * @return string A sentence describing what happened, for the notification.
     *
     * @throws RuntimeException When the provider could not be reached.
     */
    public function cancelByAdmin(Subscription $subscription, ?int $actorId = null): string
    {
        $lsSubId = $subscription->lemonsqueezy_subscription_id;

        if (! empty($lsSubId)) {
            if (! BillingSetting::current()->isConfigured()) {
                throw new RuntimeException('LemonSqueezy is not configured, so this subscription cannot be cancelled at the provider.');
            }

            try {
                $attributes = LemonSqueezy::fromSettings()->cancel($lsSubId);
            } catch (Throwable $e) {
                Log::error('Admin LemonSqueezy cancel failed', [
                    'subscription_id' => $subscription->getKey(),
                    'ls_sub' => $lsSubId,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException('LemonSqueezy rejected the cancellation — nothing was changed locally.');
            }

            $endsAt = ! empty($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null;

            $this->markCancelled(
                $lsSubId,
                $attributes['status'] ?? 'cancelled',
                $endsAt,
                UserPlanHistory::SOURCE_ADMIN,
                $actorId,
            );

            $until = ($endsAt ?: $subscription->refresh()->ends_at)?->toFormattedDayDateString();

            return 'Cancelled at LemonSqueezy — it will not renew and access continues until '.($until ?: 'the period ends').'.';
        }

        PlanHistory::withoutObserver(function () use ($subscription, $actorId): void {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $this->history->recordOnce($subscription, UserPlanHistory::EVENT_CANCELLED, UserPlanHistory::SOURCE_ADMIN, $actorId, [
                'cancelled_at' => $subscription->cancelled_at,
                'note' => 'Cancelled by admin',
            ]);
        });

        return 'Plan cancelled. The user loses it immediately and will be put on the Free plan on their next visit.';
    }

    /**
     * Supersede (cancel, history kept) every active subscription a user holds.
     */
    private function supersedeActiveFor(int $userId, string $source, ?int $actorId, string $note, ?int $exceptId = null): void
    {
        $previous = Subscription::query()
            ->where('user_id', $userId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->with('plan')
            ->get();

        foreach ($previous as $old) {
            $old->update([
                'status' => Subscription::STATUS_SUPERSEDED,
                'cancelled_at' => $old->cancelled_at ?: now(),
            ]);

            $this->history->record($old, UserPlanHistory::EVENT_SUPERSEDED, $source, $actorId, [
                'cancelled_at' => $old->cancelled_at,
                'note' => $note,
            ]);
        }
    }

    /** Same, but keeping the subscription that should remain the active one. */
    private function supersedeOtherActive(Subscription $keep, string $source, ?int $actorId, string $note): void
    {
        $this->supersedeActiveFor((int) $keep->user_id, $source, $actorId, $note, (int) $keep->getKey());
    }

    /**
     * Mark a subscription whose period has run out as expired (history kept).
     * Status alone never expires a row — `scopeActive` filters on ends_at — so
     * this is what turns a lapsed row into a logged expiry.
     */
    public function expire(Subscription $subscription, string $source = UserPlanHistory::SOURCE_SYSTEM): void
    {
        PlanHistory::withoutObserver(function () use ($subscription, $source): void {
            $subscription->update([
                'status' => Subscription::STATUS_EXPIRED,
                'expired_at' => $subscription->expired_at ?: now(),
            ]);

            $this->history->recordOnce($subscription, UserPlanHistory::EVENT_EXPIRED, $source, null, [
                'expired_at' => $subscription->expired_at,
            ]);
        });
    }
}
