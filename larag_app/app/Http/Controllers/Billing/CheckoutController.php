<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Services\Billing\LemonSqueezy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutController extends Controller
{
    /**
     * Start a checkout (new subscription) or an upgrade (variant swap) for the
     * authenticated user, enforcing the plan rules server-side:
     *  - with an active subscription, only a strictly-higher-credit plan is
     *    allowed (same or lower is rejected);
     *  - an active LemonSqueezy subscription is upgraded in place (swap);
     *  - otherwise a fresh hosted checkout is created.
     */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'interval' => ['nullable', 'in:monthly,yearly'],
        ]);

        $user = $request->user();
        // Users can only self-checkout PUBLIC plans; hidden plans are assigned
        // by an admin (admin panel), never purchasable directly.
        $plan = Plan::query()->where('is_active', true)->where('is_public', true)->findOrFail($data['plan_id']);
        $interval = Plan::normalizeInterval($data['interval'] ?? Plan::INTERVAL_MONTHLY);

        $settings = BillingSetting::current();
        if (! $settings->isConfigured()) {
            return redirect()->route('pricing')->with('error', 'Online checkout is not available right now. Please contact us.');
        }

        $variantId = $plan->variantFor($interval);
        if (empty($variantId)) {
            return redirect()->route('pricing')->with('error', 'This plan is not purchasable on the selected billing cycle yet. Please contact us.');
        }

        $active = $user->activeSubscription()->with('plan')->first();

        // Enforce the change rule while an active subscription exists. Tiers rank
        // by the monthly base credit limit; within the same tier, switching from
        // monthly to yearly is allowed (everything else is same-or-lower → blocked).
        if ($active) {
            $currentTier = (int) ($active->plan?->credit_limit ?? -1);
            $currentInterval = Plan::normalizeInterval($active->billing_interval);
            $targetTier = (int) $plan->credit_limit;

            $isUpgrade = $targetTier > $currentTier
                || ($targetTier === $currentTier && $interval === Plan::INTERVAL_YEARLY && $currentInterval === Plan::INTERVAL_MONTHLY);

            if (! $isUpgrade) {
                $message = ((int) $active->plan_id === (int) $plan->getKey() && $currentInterval === $interval)
                    ? 'You are already on this plan.'
                    : 'You can only upgrade to a plan with more credits (or switch your current plan to yearly).';

                return redirect()->route('pricing')->with('error', $message);
            }
        }

        // New subscription OR upgrade → always a fresh LemonSqueezy checkout. On
        // completion the webhook provisions the new subscription and cancels the
        // previous one (if any), so there is never double billing.
        try {
            $url = LemonSqueezy::fromSettings()->createCheckout(
                $user,
                $plan,
                (string) $variantId,
                $interval,
                route('app.billing'),
            );
        } catch (Throwable $e) {
            Log::error('LemonSqueezy checkout failed', ['user_id' => $user->getKey(), 'plan_id' => $plan->getKey(), 'error' => $e->getMessage()]);

            return redirect()->route('pricing')->with('error', 'We could not start your checkout. Please try again or contact us.');
        }

        return redirect()->away($url);
    }
}
