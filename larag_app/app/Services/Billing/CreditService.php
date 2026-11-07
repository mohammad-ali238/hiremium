<?php

namespace App\Services\Billing;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Atomically deduct credits from the subscription.
     * Throws InsufficientCreditsException if balance would go below zero.
     */
    public function charge(Subscription $subscription, int $credits = 1): Subscription
    {
        return DB::transaction(function () use ($subscription, $credits) {
            $fresh = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->isUsable()) {
                throw new InsufficientCreditsException;
            }

            if ($fresh->credits_remaining < $credits) {
                throw new InsufficientCreditsException;
            }

            $fresh->decrement('credits_remaining', $credits);

            return $fresh->refresh();
        });
    }

    /**
     * Refund credits back to a subscription (used when AI call fails after charge).
     */
    public function refund(Subscription $subscription, int $credits = 1): Subscription
    {
        return DB::transaction(function () use ($subscription, $credits) {
            $fresh = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $fresh->increment('credits_remaining', $credits);

            return $fresh->refresh();
        });
    }
}
