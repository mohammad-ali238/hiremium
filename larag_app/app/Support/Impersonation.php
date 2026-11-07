<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin "login as" (impersonation) state. Everything lives in the session —
 * nothing is persisted and nothing is exposed in a URL — and this class is the
 * only place that touches those keys.
 *
 * The flow is deliberately two-step: the admin-only Filament action records a
 * single-use hand-off (`handOff`), and the start route consumes it (`takeTarget`)
 * before swapping the authenticated user. That way no URL can be replayed,
 * shared or guessed to become another user.
 */
class Impersonation
{
    /** The admin who started it — its presence IS "impersonation is active". */
    private const IMPERSONATOR = 'impersonate.impersonator_id';

    /** The admin page to return to when switching back. */
    private const RETURN_URL = 'impersonate.return_url';

    /** Single-use hand-off written by the admin action, read by the start route. */
    private const TARGET = 'impersonate.target_id';

    public static function isActive(): bool
    {
        return self::session()?->has(self::IMPERSONATOR) === true;
    }

    public static function impersonatorId(): ?int
    {
        $id = self::session()?->get(self::IMPERSONATOR);

        return $id === null ? null : (int) $id;
    }

    /** The admin behind the current impersonation, when they still exist and are still an admin. */
    public static function impersonator(): ?User
    {
        $id = self::impersonatorId();

        if ($id === null) {
            return null;
        }

        $admin = User::find($id);

        return $admin && $admin->is_admin ? $admin : null;
    }

    /** Record who to impersonate and where to come back to. Admin-only callers. */
    public static function handOff(int $targetId, ?string $returnUrl = null): void
    {
        $session = self::session();

        if (! $session) {
            return;
        }

        $session->put(self::TARGET, $targetId);
        $session->put(self::RETURN_URL, self::safeAdminUrl($returnUrl));
    }

    /** Consume the hand-off — it can only be read once. */
    public static function takeTarget(): ?int
    {
        $id = self::session()?->pull(self::TARGET);

        return $id === null ? null : (int) $id;
    }

    public static function begin(User $admin, User $target): void
    {
        self::session()?->put(self::IMPERSONATOR, $admin->getKey());

        Log::warning('Impersonation started', [
            'admin_id' => $admin->getKey(),
            'admin_email' => $admin->email,
            'target_id' => $target->getKey(),
            'target_email' => $target->email,
            'ip' => request()->ip(),
        ]);
    }

    public static function end(): void
    {
        $session = self::session();

        if (! $session) {
            return;
        }

        Log::warning('Impersonation ended', [
            'admin_id' => $session->get(self::IMPERSONATOR),
            'target_id' => Auth::id(),
            'ip' => request()->ip(),
        ]);

        $session->forget([self::IMPERSONATOR, self::RETURN_URL, self::TARGET]);
    }

    /** Where "switch back" lands: the admin page it was started from. */
    public static function returnUrl(): string
    {
        return self::safeAdminUrl(self::session()?->get(self::RETURN_URL));
    }

    /**
     * Only ever return to an admin page on this host — a stored value is never
     * trusted as a redirect target (no open redirect, even if the session is
     * somehow tampered with).
     */
    public static function safeAdminUrl(?string $url): string
    {
        $fallback = url('/admin');

        if (! is_string($url) || $url === '') {
            return $fallback;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return $fallback;
        }

        // Same host only (a relative path has no host).
        if (isset($parts['host']) && $parts['host'] !== parse_url(url('/'), PHP_URL_HOST)) {
            return $fallback;
        }

        $path = $parts['path'] ?? '';
        $adminPath = rtrim(parse_url(url('/admin'), PHP_URL_PATH) ?: '/admin', '/');

        if (! Str::startsWith($path, $adminPath)) {
            return $fallback;
        }

        return $url;
    }

    /**
     * The request's session store. Resolved from the container rather than the
     * request so it also works before/outside a request (console, queue) — where
     * it is simply an unstarted, empty store, i.e. "not impersonating".
     */
    private static function session(): ?Store
    {
        return app()->bound('session.store') ? app('session.store') : null;
    }
}
