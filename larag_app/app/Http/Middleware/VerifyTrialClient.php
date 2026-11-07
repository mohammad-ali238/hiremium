<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft client guard for the free-trial claim/status endpoints. Mirrors the
 * addons project's fingerprint check: format-validate the reported site domain
 * + IP, apply a WordPress User-Agent heuristic, and reject blocklisted
 * domains/IPs (both the connection IP and the site-reported IP). Trust is soft —
 * the real dedupe is "one trial per email" at the controller.
 */
class VerifyTrialClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $siteUrl = (string) $request->input('site_url', '');
        $domain = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        $reportedIp = (string) $request->input('ip', '');

        $validDomain = $domain !== '' && (bool) preg_match(
            '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
            $domain
        );

        // Reported IP is optional but, when present, must be a valid IP.
        $validReportedIp = $reportedIp === '' || filter_var($reportedIp, FILTER_VALIDATE_IP) !== false;

        $isWordPress = ! config('trial.require_wordpress_ua')
            || str_contains((string) $request->userAgent(), 'WordPress');

        $blocked = (array) config('trial.blocked', []);
        $isBlocked = in_array($domain, $blocked['domains'] ?? [], true)
            || in_array($request->ip(), $blocked['ips'] ?? [], true)
            || in_array($reportedIp, $blocked['ips'] ?? [], true);

        if (! $validDomain || ! $validReportedIp || ! $isWordPress || $isBlocked) {
            return response()->json([
                'error' => [
                    'code' => 'request_rejected',
                    'message' => 'Request rejected.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
