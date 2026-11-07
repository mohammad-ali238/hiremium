<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes new account sign-ups when the admin has disabled registration. Existing users
 * and every other feature are unaffected — only the register page/POST are gated.
 */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteSettings::registrationEnabled()) {
            return redirect()->route('login')
                ->with('status', 'New account registration is currently closed.');
        }

        return $next($request);
    }
}
