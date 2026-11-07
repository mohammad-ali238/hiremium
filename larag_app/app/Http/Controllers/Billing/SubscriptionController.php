<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\UserPlanHistory;
use App\Services\Billing\LemonSqueezy;
use App\Services\Billing\SubscriptionProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-serve subscription management from the dashboard: cancel a renewing
 * subscription (keeping access until the period end) or resume one that was
 * cancelled but is still within its paid period. Both talk to LemonSqueezy and
 * then optimistically sync the local subscription; the matching webhook later
 * reconciles the exact dates.
 */
class SubscriptionController extends Controller
{
    /**
     * Cancel the user's active subscription (stops future renewals).
     */
    public function cancel(Request $request, SubscriptionProvisioner $provisioner): RedirectResponse
    {
        $subscription = $this->manageableSubscription($request);

        if (! $subscription) {
            return redirect()->route('app.billing')->with('error', 'No active subscription found to cancel.');
        }

        if ($subscription->lemonsqueezy_status === 'cancelled') {
            return redirect()->route('app.billing')->with('billing_status', 'Your subscription is already set to cancel.');
        }

        if (! BillingSetting::current()->isConfigured()) {
            return redirect()->route('app.billing')->with('error', 'Subscription management is unavailable right now. Please contact us.');
        }

        try {
            $attributes = LemonSqueezy::fromSettings()->cancel($subscription->lemonsqueezy_subscription_id);
        } catch (Throwable $e) {
            Log::error('LemonSqueezy cancel failed', ['user_id' => $request->user()->getKey(), 'subscription_id' => $subscription->getKey(), 'error' => $e->getMessage()]);

            return redirect()->route('app.billing')->with('error', 'We could not cancel your subscription. Please try again or contact us.');
        }

        $endsAt = ! empty($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null;
        $provisioner->markCancelled(
            $subscription->lemonsqueezy_subscription_id,
            $attributes['status'] ?? 'cancelled',
            $endsAt,
            UserPlanHistory::SOURCE_SELF,
            $request->user()->getKey(),
        );

        // Flag the current order entry as cancelled right away (rather than
        // waiting for the webhook), so My Orders reflects it immediately.
        PaymentTransaction::query()
            ->where('lemonsqueezy_subscription_id', $subscription->lemonsqueezy_subscription_id)
            ->whereNull('cancelled_at')
            ->latest('id')
            ->first()
            ?->update(['cancelled_at' => now()]);

        $until = ($endsAt ?: $subscription->ends_at)->toFormattedDayDateString();

        return redirect()->route('app.billing')->with('billing_status', "Your subscription has been cancelled. You keep your plan and remaining credits until {$until}, after which it will not renew.");
    }

    /**
     * Resume a cancelled-but-still-active subscription so it renews again.
     */
    public function resume(Request $request, SubscriptionProvisioner $provisioner): RedirectResponse
    {
        $subscription = $this->manageableSubscription($request);

        if (! $subscription || $subscription->lemonsqueezy_status !== 'cancelled') {
            return redirect()->route('app.billing')->with('error', 'There is no cancelled subscription to resume.');
        }

        if (! BillingSetting::current()->isConfigured()) {
            return redirect()->route('app.billing')->with('error', 'Subscription management is unavailable right now. Please contact us.');
        }

        try {
            $attributes = LemonSqueezy::fromSettings()->resume($subscription->lemonsqueezy_subscription_id);
        } catch (Throwable $e) {
            Log::error('LemonSqueezy resume failed', ['user_id' => $request->user()->getKey(), 'subscription_id' => $subscription->getKey(), 'error' => $e->getMessage()]);

            return redirect()->route('app.billing')->with('error', 'We could not resume your subscription. Please try again or contact us.');
        }

        $renewsAt = ! empty($attributes['renews_at']) ? Carbon::parse($attributes['renews_at']) : null;
        $provisioner->markResumed(
            $subscription->lemonsqueezy_subscription_id,
            $attributes['status'] ?? 'active',
            $renewsAt,
            UserPlanHistory::SOURCE_SELF,
            $request->user()->getKey(),
        );

        // The subscription is renewing again — clear the cancellation flag on its
        // order entry immediately (rather than waiting for the webhook).
        PaymentTransaction::query()
            ->where('lemonsqueezy_subscription_id', $subscription->lemonsqueezy_subscription_id)
            ->whereNotNull('cancelled_at')
            ->update(['cancelled_at' => null]);

        return redirect()->route('app.billing')->with('billing_status', 'Your subscription has been resumed and will renew as normal.');
    }

    /**
     * Redirect the user to LemonSqueezy to update their payment method. The URL
     * is fetched fresh (it is short-lived), so we never store a stale link.
     */
    public function updatePayment(Request $request): RedirectResponse
    {
        $subscription = $this->manageableSubscription($request);

        if (! $subscription || ! BillingSetting::current()->isConfigured()) {
            return redirect()->route('app.billing')->with('error', 'Payment management is unavailable right now. Please contact us.');
        }

        try {
            $url = LemonSqueezy::fromSettings()->paymentUpdateUrl($subscription->lemonsqueezy_subscription_id);
        } catch (Throwable $e) {
            Log::error('LemonSqueezy payment-update URL failed', ['user_id' => $request->user()->getKey(), 'subscription_id' => $subscription->getKey(), 'error' => $e->getMessage()]);
            $url = null;
        }

        if (! $url) {
            return redirect()->route('app.billing')->with('error', 'We could not open the payment update page. Please try again or contact us.');
        }

        return redirect()->away($url);
    }

    /**
     * The user's current, still-valid subscription that is backed by a
     * LemonSqueezy subscription id (manually-assigned plans cannot be managed here).
     */
    private function manageableSubscription(Request $request): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $request->user()->getKey())
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->whereNotNull('lemonsqueezy_subscription_id')
            ->latest('id')
            ->first();
    }
}
