<?php

namespace App\Services\Trial;

use App\Models\TrialCredit;
use Illuminate\Support\Str;

/**
 * Creation + activation of free-trial grants (key generation, email
 * canonicalisation for dedupe, verification → active transition).
 */
class TrialService
{
    /**
     * Create a pending (unverified, zero-credit) grant.
     */
    public function createPending(string $email, ?string $siteUrl, ?string $ip, ?string $reportedIp): TrialCredit
    {
        return TrialCredit::create([
            'email' => strtolower(trim($email)),
            'email_canonical' => $this->canonicalize($email),
            'uuid' => (string) Str::uuid(),
            'site_url' => $siteUrl,
            'site_domain' => self::domainOf($siteUrl),
            'ip' => $ip,
            'reported_ip' => $reportedIp,
            'status' => TrialCredit::STATUS_PENDING,
            'credits_total' => 0,
            'credits_used' => 0,
        ]);
    }

    /**
     * Verify + activate a grant: issue an api_key + redirect_token, set the
     * credit allowance and expiry. Idempotent for an already-verified grant.
     */
    public function activate(TrialCredit $trial, ?int $credits = null, ?int $days = null): TrialCredit
    {
        $credits ??= (int) config('trial.credits', TrialCredit::DEFAULT_CREDITS);
        $days ??= (int) config('trial.days', TrialCredit::DEFAULT_DAYS);

        if (empty($trial->api_key)) {
            $trial->api_key = $this->generateApiKey();
        }
        if (empty($trial->redirect_token)) {
            $trial->redirect_token = Str::random(48);
        }

        $trial->status = TrialCredit::STATUS_VERIFIED;
        $trial->credits_total = $credits;
        $trial->verified_at = $trial->verified_at ?? now();
        $trial->expires_at = now()->addDays($days);
        $trial->save();

        return $trial;
    }

    /**
     * A 64-char, pipe-free api_key (so it never collides with a Sanctum
     * "id|token" and is safely usable as a Bearer credential).
     */
    public function generateApiKey(): string
    {
        return 'wtc_'.bin2hex(random_bytes(30));
    }

    /**
     * Normalised email used only as a duplicate signal. Strips +tag sub-
     * addressing for every domain, and additionally strips dots + unifies the
     * host for Gmail.
     */
    public function canonicalize(string $email): string
    {
        $email = strtolower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        // Sub-addressing (user+tag@…) → user@… for all providers.
        $local = explode('+', $local)[0];

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    /**
     * Normalised host of a URL (for per-site caps), or '' when unparseable.
     */
    public static function domainOf(?string $url): string
    {
        if (! $url) {
            return '';
        }

        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
