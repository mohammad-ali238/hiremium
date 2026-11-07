<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Exceptions\Api\NoActiveSubscriptionException;
use App\Models\Subscription;
use App\Models\TrialCredit;
use App\Services\Billing\CreditService;
use App\Services\Billing\TrialCreditService;
use App\Services\Billing\UserPlanGuard;
use App\Services\Credits\SubscriptionCreditSource;
use App\Services\Credits\TrialCreditSource;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified auth + credit gate for the AI endpoints. Accepts EITHER:
 *   - a Sanctum personal access token → resolves the user + active subscription
 *     (paid path, behaviour identical to auth:sanctum + subscription.active), or
 *   - a free-trial api_key → resolves the trial grant.
 * In both cases a CreditSource is attached to the request as `credit_source`.
 */
class AuthorizeAiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            throw new AuthenticationException;
        }

        // 1) Paid path — Sanctum personal access token.
        $token = PersonalAccessToken::findToken($bearer);
        if ($token) {
            if ($token->expires_at && $token->expires_at->isPast()) {
                throw new AuthenticationException;
            }

            $user = $token->tokenable;
            if (! $user) {
                throw new AuthenticationException;
            }

            $token->forceFill(['last_used_at' => now()])->save();
            $request->setUserResolver(fn () => $user);

            // Same guarantee as the /app dashboard: a lapsed free period is
            // rolled over here too, so an API-only account is never left
            // plan-less in the window before the nightly command runs.
            $subscription = app(UserPlanGuard::class)->ensure($user);

            if (! $subscription) {
                throw new NoActiveSubscriptionException;
            }
            if ($subscription->credits_remaining <= 0) {
                throw new InsufficientCreditsException;
            }

            $request->attributes->set(
                'credit_source',
                new SubscriptionCreditSource(app(CreditService::class), $user, $subscription)
            );

            return $next($request);
        }

        // 2) Free-trial path — trial api_key.
        $trial = TrialCredit::findByApiKey($bearer);
        if ($trial) {
            if (! $trial->isActive()) {
                throw new NoActiveSubscriptionException('Your free trial has expired.');
            }
            if ($trial->credits_remaining <= 0) {
                throw new InsufficientCreditsException('Your free trial credits have been used up.');
            }

            $request->attributes->set(
                'credit_source',
                new TrialCreditSource(app(TrialCreditService::class), $trial)
            );

            return $next($request);
        }

        // Neither a valid token nor a valid trial key.
        throw new AuthenticationException;
    }
}
