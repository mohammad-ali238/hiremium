<?php

namespace App\Support;

/**
 * Multi-account detection signals (same process as the hirin project). One person
 * registering many accounts to farm credits is caught by stacking cheap signals:
 * canonical email comparison (defeats +tag / gmail-dot aliases), a disposable-domain
 * blocklist, the registration IP, and a long-lived device cookie. Registration blocks
 * the clear cases; Filament shows linked accounts for the rest.
 */
class AccountSignals
{
    /** Long-lived guest cookie identifying a browser profile. */
    public const DEVICE_COOKIE = 'hiremium_did';

    /** Max new accounts from one IP address per trailing 24 hours. */
    public const MAX_PER_IP_PER_DAY = 3;

    /** Max accounts ever registered from one device cookie. */
    public const MAX_PER_DEVICE = 2;

    /**
     * Canonical form of an email for duplicate detection: lowercase, the +tag stripped
     * from the local part, and (for gmail) dots removed from the local part —
     * "John.Doe+x@Gmail.com" → "johndoe@gmail.com".
     */
    public static function canonicalEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (($pos = strpos($local, '+')) !== false) {
            $local = substr($local, 0, $pos);
        }

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    /** Whether the email's domain (or its registrable parent) is a known disposable inbox. */
    public static function isDisposable(string $email): bool
    {
        $domain = mb_strtolower(trim((string) strrchr($email, '@'), '@'));

        if ($domain === '') {
            return false;
        }

        $blocked = config('disposable_email', []);

        if (in_array($domain, $blocked, true)) {
            return true;
        }

        // Catch subdomains of blocked domains (a.mailinator.com → mailinator.com).
        foreach ($blocked as $bad) {
            if (str_ends_with($domain, '.'.$bad)) {
                return true;
            }
        }

        return false;
    }

    /** Stable hash of the device cookie value (never store the raw cookie). */
    public static function deviceHash(?string $cookieValue): ?string
    {
        return filled($cookieValue) ? hash('sha256', $cookieValue) : null;
    }
}
