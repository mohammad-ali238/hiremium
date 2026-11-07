<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionProvisioner;
use App\Services\Billing\UserPlanGuard;
use Illuminate\Console\Command;

/**
 * Nightly plan housekeeping:
 *
 *  1. Activate subscriptions the admin scheduled for a later date that have now
 *     reached their start date (superseding whatever covered the gap).
 *  2. Roll over FREE subscriptions whose 30-day period has ended: the lapsed row
 *     is marked expired and a fresh Free subscription is assigned, which is what
 *     refreshes the monthly credit allotment. A lapsed PAID subscription only has
 *     its expiry recorded (the provider owns its renewal), so the admin never
 *     sees a finished period still flagged active — unless the provider is still
 *     renewing it, in which case it is extended by the grace window instead.
 *
 * Every step is written to the user's plan history, so each cycle is recorded.
 *
 * The /app guard does the same thing the moment the user opens the dashboard;
 * this covers accounts that only ever call the API. Paid subscriptions are
 * refreshed by the billing renewal, so they are intentionally excluded.
 * Scheduled daily; safe to re-run.
 */
class ResetMonthlyCredits extends Command
{
    protected $signature = 'hiremium:reset-monthly-credits';

    protected $description = 'Activate scheduled plans that came due and roll over ended free periods';

    public function handle(UserPlanGuard $guard, SubscriptionProvisioner $provisioner): int
    {
        // 1) A plan the admin scheduled for a later date has started, so the
        //    account now shows two usable subscriptions — exactly the state
        //    activateDue() resolves (latest start wins, the other is superseded).
        $contested = Subscription::query()
            ->active()
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        $activated = 0;

        foreach (User::whereIn('id', $contested)->get() as $user) {
            if ($provisioner->activateDue($user)) {
                $activated++;
            }
        }

        // 2) Periods that ended. A free one rolls over into a fresh period
        //    (which is what refreshes its credits); a paid one just has its
        //    expiry recorded — the provider owns its renewal, and the user is
        //    put on Free lazily the next time they use the app or the API.
        $lapsed = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->with('plan', 'user')
            ->get();

        $rolled = 0;
        $expired = 0;
        $graced = 0;

        foreach ($lapsed as $subscription) {
            if ($subscription->plan?->isFree() && $subscription->user) {
                // ensure() expires every lapsed row for the user, then assigns Free.
                $guard->ensure($subscription->user);
                $rolled++;

                continue;
            }

            // Paid: expire it, or extend it when the provider is still renewing.
            if ($guard->settleLapsed($subscription)) {
                $expired++;
            } else {
                $graced++;
            }
        }

        $this->info("Activated {$activated} scheduled plan(s); rolled over {$rolled} free subscription(s); expired {$expired} and gave {$graced} a renewal grace.");

        return self::SUCCESS;
    }
}
