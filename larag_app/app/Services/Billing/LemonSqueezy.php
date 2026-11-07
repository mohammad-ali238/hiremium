<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin LemonSqueezy REST API client. Reads credentials from the admin-managed
 * BillingSetting singleton and speaks the JSON:API format LemonSqueezy expects.
 */
class LemonSqueezy
{
    private const BASE_URL = 'https://api.lemonsqueezy.com/v1';

    public function __construct(private readonly BillingSetting $settings) {}

    public static function fromSettings(): self
    {
        return new self(BillingSetting::current());
    }

    /**
     * Create a hosted checkout for a plan and return its URL. The buyer is
     * carried through as custom data so the webhook provisions the right user.
     */
    public function createCheckout(User $user, Plan $plan, string $variantId, string $interval, string $redirectUrl): string
    {
        $response = $this->request()->post(self::BASE_URL.'/checkouts', [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'checkout_data' => [
                        'email' => $user->email,
                        'custom' => [
                            'user_id' => (string) $user->getKey(),
                            'plan_id' => (string) $plan->getKey(),
                            'interval' => $interval,
                        ],
                    ],
                    'product_options' => [
                        'redirect_url' => $redirectUrl,
                    ],
                ],
                'relationships' => [
                    'store' => [
                        'data' => ['type' => 'stores', 'id' => (string) $this->settings->store_id],
                    ],
                    'variant' => [
                        'data' => ['type' => 'variants', 'id' => (string) $variantId],
                    ],
                ],
            ],
        ]);

        $url = $response->json('data.attributes.url');

        if (! $response->successful() || ! $url) {
            throw new RuntimeException('LemonSqueezy checkout could not be created: '.$response->body());
        }

        return $url;
    }

    /**
     * Change an existing subscription to a new plan's variant (an upgrade).
     * LemonSqueezy prorates and keeps the single subscription.
     */
    public function swapVariant(string $lsSubscriptionId, string $variantId): void
    {
        $response = $this->request()->patch(self::BASE_URL.'/subscriptions/'.$lsSubscriptionId, [
            'data' => [
                'type' => 'subscriptions',
                'id' => (string) $lsSubscriptionId,
                'attributes' => [
                    'variant_id' => (int) $variantId,
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('LemonSqueezy subscription upgrade failed: '.$response->body());
        }
    }

    /**
     * Cancel a subscription on LemonSqueezy (stays active until the period end).
     * Used both to retire a superseded subscription when a user upgrades via a
     * new checkout, and for a user-initiated cancellation from the dashboard.
     *
     * @return array<string, mixed> The subscription's attributes after cancelling
     *                              (includes the final `ends_at`), or [] if absent.
     */
    public function cancel(string $lsSubscriptionId): array
    {
        $response = $this->request()->delete(self::BASE_URL.'/subscriptions/'.$lsSubscriptionId);

        if (! $response->successful()) {
            throw new RuntimeException('LemonSqueezy subscription cancellation failed: '.$response->body());
        }

        return (array) $response->json('data.attributes', []);
    }

    /**
     * Resume (un-cancel) a subscription that was cancelled but is still within
     * its paid period, so it renews again. LemonSqueezy accepts a PATCH with
     * `cancelled = false`.
     *
     * @return array<string, mixed> The subscription's attributes after resuming.
     */
    public function resume(string $lsSubscriptionId): array
    {
        $response = $this->request()->patch(self::BASE_URL.'/subscriptions/'.$lsSubscriptionId, [
            'data' => [
                'type' => 'subscriptions',
                'id' => (string) $lsSubscriptionId,
                'attributes' => [
                    'cancelled' => false,
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('LemonSqueezy subscription resume failed: '.$response->body());
        }

        return (array) $response->json('data.attributes', []);
    }

    /**
     * Fetch a fresh, signed "update payment method" URL for a subscription.
     * These URLs are short-lived, so we generate one on demand rather than
     * storing it. Returns null if LemonSqueezy does not provide one.
     */
    public function paymentUpdateUrl(string $lsSubscriptionId): ?string
    {
        $response = $this->request()->get(self::BASE_URL.'/subscriptions/'.$lsSubscriptionId);

        if (! $response->successful()) {
            throw new RuntimeException('LemonSqueezy subscription lookup failed: '.$response->body());
        }

        return $response->json('data.attributes.urls.update_payment_method');
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->settings->api_key)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->withOptions(['verify' => SiteSettings::sslVerifyEnabled()])
            ->timeout(20);
    }
}
