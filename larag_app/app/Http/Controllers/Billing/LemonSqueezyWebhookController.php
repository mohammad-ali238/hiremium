<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanHistory;
use App\Services\Billing\LemonSqueezy;
use App\Services\Billing\SubscriptionProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives LemonSqueezy subscription webhooks. Unauthenticated but verified by
 * HMAC signature. Registered on the CSRF-free api route group.
 *
 * Credit provisioning is driven by the subscription-object events
 * (`subscription_created`, `subscription_updated`). The My Orders ledger keeps
 * ONE row per order: a `success` row is created on `subscription_created` (the
 * event that always fires on a completed purchase) and its amount is filled in
 * from the matching payment; renewals add a new `success` row, and failed
 * payments add their own `failed` row. A cancellation does NOT add a row — it
 * stamps `cancelled_at` on the subscription's current order entry (cleared again
 * if the subscription is resumed).
 */
class LemonSqueezyWebhookController extends Controller
{
    /** Events whose payload `data` is an invoice (not the subscription). */
    private const INVOICE_EVENTS = ['subscription_payment_success', 'subscription_payment_failed'];

    public function __construct(private readonly SubscriptionProvisioner $provisioner) {}

    public function __invoke(Request $request): JsonResponse
    {
        $settings = BillingSetting::current();

        if (! $settings->enabled || empty($settings->signing_secret)) {
            return response()->json(['message' => 'Billing disabled.'], 403);
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, (string) $settings->signing_secret);
        $received = (string) $request->header('X-Signature');

        if (! hash_equals($expected, $received)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $body = $request->json()->all();
        $eventName = (string) ($request->header('X-Event-Name') ?: data_get($body, 'meta.event_name', ''));
        $attributes = (array) data_get($body, 'data.attributes', []);
        $dataId = (string) data_get($body, 'data.id', '');
        $custom = (array) data_get($body, 'meta.custom_data', []);

        // The subscription id lives in different places per event type.
        $lsSubId = in_array($eventName, self::INVOICE_EVENTS, true)
            ? (string) data_get($attributes, 'subscription_id', '')
            : $dataId;

        $variantId = (string) data_get($attributes, 'variant_id', '');
        $resolved = $variantId !== '' ? Plan::resolveByVariant($variantId) : null;
        $plan = $resolved['plan'] ?? null;
        $interval = $resolved['interval'] ?? Plan::normalizeInterval($custom['interval'] ?? null);
        $status = (string) data_get($attributes, 'status', '');
        $renewsAt = ($raw = data_get($attributes, 'renews_at')) ? Carbon::parse($raw) : now();
        $total = data_get($attributes, 'total') !== null ? (int) data_get($attributes, 'total') : null;
        $currency = data_get($attributes, 'currency');
        $customerId = (string) data_get($attributes, 'customer_id', '') ?: null;

        try {
            switch ($eventName) {
                case 'subscription_created':
                    $user = isset($custom['user_id']) ? User::find((int) $custom['user_id']) : null;
                    $plan = $plan ?: (isset($custom['plan_id']) ? Plan::find((int) $custom['plan_id']) : null);

                    if (! $user || ! $plan) {
                        Log::warning('LemonSqueezy subscription_created without resolvable user/plan', ['ls_sub' => $lsSubId, 'custom' => $custom, 'variant' => $variantId]);
                        break;
                    }

                    // Idempotent: re-provision + re-record only once per subscription.
                    if (Subscription::query()->where('lemonsqueezy_subscription_id', $lsSubId)->exists()) {
                        break;
                    }

                    // Retire the user's previous LS subscription (upgrade via a
                    // fresh checkout supersedes it) so they aren't double-billed.
                    $oldLsSubId = Subscription::query()
                        ->where('user_id', $user->getKey())
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->whereNotNull('lemonsqueezy_subscription_id')
                        ->where('lemonsqueezy_subscription_id', '!=', $lsSubId)
                        ->value('lemonsqueezy_subscription_id');

                    $subscription = $this->provisioner->provision($user, $plan, [
                        'interval' => $interval,
                        'lemonsqueezy_subscription_id' => $lsSubId,
                        'lemonsqueezy_order_id' => (string) data_get($attributes, 'order_id', ''),
                        'lemonsqueezy_customer_id' => $customerId ?? '',
                        'lemonsqueezy_status' => $status,
                        'renews_at' => $renewsAt,
                    ], source: UserPlanHistory::SOURCE_PAYMENT);

                    if ($oldLsSubId) {
                        try {
                            LemonSqueezy::fromSettings()->cancel($oldLsSubId);
                        } catch (Throwable $e) {
                            Log::error('Failed to cancel superseded LemonSqueezy subscription', ['old' => $oldLsSubId, 'error' => $e->getMessage()]);
                        }
                    }

                    // One success order row per purchase (amount filled by the
                    // payment event that follows).
                    $this->recordOrder($body, [
                        'user_id' => $subscription->user_id,
                        'subscription_id' => $subscription->getKey(),
                        'plan_id' => $subscription->plan_id,
                        'lemonsqueezy_subscription_id' => $lsSubId ?: null,
                        'lemonsqueezy_order_id' => (string) data_get($attributes, 'order_id', '') ?: null,
                        'lemonsqueezy_customer_id' => $customerId,
                        'event_name' => 'subscription_created',
                        'status' => 'success',
                        'amount_total' => $total,
                        'currency' => $currency,
                    ], dedupeColumn: 'lemonsqueezy_subscription_id', dedupeValue: $lsSubId);
                    break;

                case 'subscription_updated':
                    if ($plan) {
                        $this->provisioner->syncFromLemonSqueezy($lsSubId, $plan, $interval, $renewsAt, $status);
                    }

                    // A resumed (un-cancelled) subscription is active again, so
                    // clear the cancellation flag from its order entry.
                    if ($status === 'active' && $lsSubId !== '') {
                        $this->clearOrderCancelled($lsSubId);
                    }
                    break;

                case 'subscription_cancelled':
                    $endsAtRaw = data_get($attributes, 'ends_at');
                    $this->provisioner->markCancelled($lsSubId, $status ?: 'cancelled', $endsAtRaw ? Carbon::parse($endsAtRaw) : null);

                    // Flag the current order entry as cancelled (no separate row).
                    if ($lsSubId !== '') {
                        $this->markOrderCancelled($lsSubId);
                    }
                    break;

                case 'subscription_expired':
                    $this->provisioner->revoke($lsSubId);
                    break;

                case 'subscription_payment_success':
                    $sub = $lsSubId !== '' ? Subscription::query()->where('lemonsqueezy_subscription_id', $lsSubId)->latest('id')->first() : null;
                    $reason = (string) data_get($attributes, 'billing_reason', '');

                    // A successful charge clears any past-due flag from a prior failure.
                    if ($sub && $sub->lemonsqueezy_status === 'past_due') {
                        $this->provisioner->markStatus($lsSubId, 'active');
                    }

                    if ($reason === 'renewal' || $reason === 'updated') {
                        // A new charge → a new order row.
                        $this->recordOrder($body, [
                            'user_id' => $sub?->user_id,
                            'subscription_id' => $sub?->getKey(),
                            'plan_id' => $sub?->plan_id,
                            'lemonsqueezy_subscription_id' => $lsSubId ?: null,
                            'lemonsqueezy_order_id' => $dataId ?: null,
                            'lemonsqueezy_customer_id' => $customerId,
                            'event_name' => 'subscription_payment_success',
                            'status' => 'success',
                            'amount_total' => $total,
                            'currency' => $currency,
                        ], dedupeColumn: 'lemonsqueezy_order_id', dedupeValue: $dataId);
                    } else {
                        // Initial (or unlabelled) payment for the purchase → update
                        // the order already created by subscription_created instead
                        // of adding a second row.
                        $order = PaymentTransaction::query()
                            ->where('subscription_id', $sub?->getKey())
                            ->where('event_name', 'subscription_created')
                            ->latest('id')
                            ->first();

                        if ($order) {
                            $order->update([
                                'amount_total' => $total ?? $order->amount_total,
                                'currency' => $currency ?? $order->currency,
                                'lemonsqueezy_order_id' => $order->lemonsqueezy_order_id ?: ($dataId ?: null),
                            ]);
                        } else {
                            $this->recordOrder($body, [
                                'user_id' => $sub?->user_id,
                                'subscription_id' => $sub?->getKey(),
                                'plan_id' => $sub?->plan_id,
                                'lemonsqueezy_subscription_id' => $lsSubId ?: null,
                                'lemonsqueezy_order_id' => $dataId ?: null,
                                'lemonsqueezy_customer_id' => $customerId,
                                'event_name' => 'subscription_payment_success',
                                'status' => 'success',
                                'amount_total' => $total,
                                'currency' => $currency,
                            ], dedupeColumn: 'lemonsqueezy_order_id', dedupeValue: $dataId);
                        }
                    }
                    break;

                case 'subscription_payment_failed':
                    $sub = $lsSubId !== '' ? Subscription::query()->where('lemonsqueezy_subscription_id', $lsSubId)->latest('id')->first() : null;

                    $this->recordOrder($body, [
                        'user_id' => $sub?->user_id,
                        'subscription_id' => $sub?->getKey(),
                        'plan_id' => $sub?->plan_id,
                        'lemonsqueezy_subscription_id' => $lsSubId ?: null,
                        'lemonsqueezy_order_id' => $dataId ?: null,
                        'lemonsqueezy_customer_id' => $customerId,
                        'event_name' => 'subscription_payment_failed',
                        'status' => 'failed',
                        'amount_total' => $total,
                        'currency' => $currency,
                    ], dedupeColumn: 'lemonsqueezy_order_id', dedupeValue: $dataId);

                    // Flag the subscription past-due so the dashboard can prompt the
                    // user to update their payment method during LemonSqueezy's retries.
                    if ($lsSubId !== '') {
                        $this->provisioner->markStatus($lsSubId, 'past_due');
                    }
                    break;

                default:
                    // Other events are ignored.
                    break;
            }
        } catch (Throwable $e) {
            Log::error('LemonSqueezy webhook processing failed', ['event' => $eventName, 'ls_sub' => $lsSubId, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Insert a ledger row unless one already exists for the same event and the
     * given dedupe key (LemonSqueezy retries/duplicates webhooks).
     */
    private function recordOrder(array $body, array $attributes, string $dedupeColumn, ?string $dedupeValue): void
    {
        if (! empty($dedupeValue)
            && PaymentTransaction::query()
                ->where('event_name', $attributes['event_name'])
                ->where($dedupeColumn, $dedupeValue)
                ->exists()) {
            return;
        }

        PaymentTransaction::create($attributes + ['payload' => $body]);
    }

    /**
     * Stamp `cancelled_at` on the subscription's most recent order entry (the
     * current billing period), unless it is already flagged.
     */
    private function markOrderCancelled(string $lsSubId): void
    {
        $order = PaymentTransaction::query()
            ->where('lemonsqueezy_subscription_id', $lsSubId)
            ->whereNull('cancelled_at')
            ->latest('id')
            ->first();

        $order?->update(['cancelled_at' => now()]);
    }

    /**
     * Clear the cancellation flag from the subscription's order entries (resume).
     */
    private function clearOrderCancelled(string $lsSubId): void
    {
        PaymentTransaction::query()
            ->where('lemonsqueezy_subscription_id', $lsSubId)
            ->whereNotNull('cancelled_at')
            ->update(['cancelled_at' => null]);
    }
}
