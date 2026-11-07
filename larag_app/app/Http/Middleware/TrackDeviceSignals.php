<?php

namespace App\Http\Middleware;

use App\Models\AccountDevice;
use App\Services\Security\MultiAccountDetector;
use App\Support\AccountSignals;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records which device each account is used from (once per session) and runs the
 * multi-account detector so one browser hopping between accounts gets flagged —
 * even when the accounts were registered from different networks.
 */
class TrackDeviceSignals
{
    private const SESSION_FLAG = 'device_tracked';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip while an admin is impersonating: the admin's device must not be
        // recorded against the user's account, or the multi-account detector
        // would flag them for something they never did.
        if ($user && ! $user->is_admin && ! Impersonation::isActive()
            && $request->hasSession() && ! $request->session()->get(self::SESSION_FLAG)) {
            $deviceHash = AccountSignals::deviceHash($request->cookie(AccountSignals::DEVICE_COOKIE));

            if ($deviceHash) {
                AccountDevice::updateOrCreate(
                    ['user_id' => $user->id, 'device_hash' => $deviceHash],
                    ['last_ip' => $request->ip(), 'last_seen_at' => now()],
                );

                app(MultiAccountDetector::class)->evaluateDevice($deviceHash);
            }

            $request->session()->put(self::SESSION_FLAG, true);
        }

        return $next($request);
    }
}
