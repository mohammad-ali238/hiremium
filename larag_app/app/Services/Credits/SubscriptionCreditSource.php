<?php

namespace App\Services\Credits;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\Subscription;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\Billing\CreditService;

/**
 * CreditSource backed by a paid Subscription. Behaviour is identical to the
 * pre-abstraction controller logic (charge/refund via CreditService, log to
 * usage_logs) so the paid path is unchanged.
 */
class SubscriptionCreditSource implements CreditSource
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly User $user,
        private Subscription $subscription,
    ) {}

    public function charge(int $credits): void
    {
        $this->subscription = $this->credits->charge($this->subscription, $credits);
    }

    public function refund(int $credits): void
    {
        $this->subscription = $this->credits->refund($this->subscription, $credits);
    }

    public function remaining(): int
    {
        return (int) $this->subscription->credits_remaining;
    }

    public function ensureCanAfford(int $credits): void
    {
        if ($this->subscription->credits_remaining < $credits) {
            throw new InsufficientCreditsException(
                "This call requires {$credits} credits but only {$this->subscription->credits_remaining} remain."
            );
        }
    }

    public function logSuccess(string $endpoint, array $data): void
    {
        UsageLog::create(array_merge([
            'user_id' => $this->user->getKey(),
            'subscription_id' => $this->subscription->getKey(),
            'endpoint' => $endpoint,
            'lane' => UsageLog::LANE_SYNC,
            'status' => UsageLog::STATUS_SUCCESS,
        ], $data));
    }

    public function logFailure(string $endpoint, array $requestPayload, string $error, ?string $ip = null): void
    {
        UsageLog::create([
            'user_id' => $this->user->getKey(),
            'subscription_id' => $this->subscription->getKey(),
            'endpoint' => $endpoint,
            'lane' => UsageLog::LANE_SYNC,
            'credits_consumed' => 0,
            'request_payload' => $requestPayload,
            'status' => UsageLog::STATUS_REFUNDED,
            'error_message' => $error,
            'ip_address' => $ip,
        ]);
    }

    public function type(): string
    {
        return 'subscription';
    }
}
