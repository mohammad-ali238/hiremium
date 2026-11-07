<?php

namespace App\Http\Middleware;

use App\Support\AccountSignals;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every browser a long-lived random device cookie. Its hash is stored on users at
 * registration so repeated sign-ups from the same browser can be detected and limited.
 */
class EnsureDeviceCookie
{
    /** Two years, in minutes. */
    private const LIFETIME = 60 * 24 * 730;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->cookies->has(AccountSignals::DEVICE_COOKIE)) {
            Cookie::queue(cookie(
                name: AccountSignals::DEVICE_COOKIE,
                value: Str::random(40),
                minutes: self::LIFETIME,
                httpOnly: true,
            ));
        }

        return $next($request);
    }
}
