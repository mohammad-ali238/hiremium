<?php

namespace App\Http\Middleware;

use App\Services\Billing\UserPlanGuard;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every /app page: make sure the signed-in account holder has an active plan.
 * A user who lost theirs (cancelled, expired, deleted) or never had one is put
 * on the Free plan, with the assignment written to their plan history.
 */
class EnsureUserPlan
{
    public function __construct(private UserPlanGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not while an admin is impersonating: looking at an account must not
        // assign it a plan (and write plan history) on the user's behalf.
        if ($user && ! Impersonation::isActive()) {
            $this->guard->ensure($user);
        }

        return $next($request);
    }
}
