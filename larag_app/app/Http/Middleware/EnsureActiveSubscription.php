<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Exceptions\Api\NoActiveSubscriptionException;
use App\Services\Billing\UserPlanGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Verifies the authenticated user has an active subscription with credits.
     * Attaches the subscription to the request as `subscription` for downstream use.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Rolls a lapsed free period over before concluding there is no plan.
        $subscription = app(UserPlanGuard::class)->ensure($user);

        if (! $subscription) {
            throw new NoActiveSubscriptionException;
        }

        if ($subscription->credits_remaining <= 0) {
            throw new InsufficientCreditsException;
        }

        $request->attributes->set('subscription', $subscription);

        return $next($request);
    }
}
