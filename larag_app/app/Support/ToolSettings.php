<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Free Tools switches, limits and per-tool AI overrides (Filament → Free Tools
 * → Settings). Stored as one key/value JSON row, exactly like SiteSettings.
 *
 * Every getter falls back to config('tools.*') so the tools work correctly
 * before an admin has ever opened the settings page.
 */
class ToolSettings
{
    public const SETTINGS_KEY = 'tool_settings';

    /** SEO fields stored per tool page (same shape as SiteSettings). */
    public const SEO_FIELDS = ['title', 'description', 'keywords', 'image'];

    /* ------------------------------------------------------------------ */
    /* Switches */
    /* ------------------------------------------------------------------ */

    /** Master switch for the whole Free Tools section. */
    public static function enabled(): bool
    {
        return (bool) (self::all()['enabled'] ?? config('tools.enabled', true));
    }

    public static function setEnabled(bool $enabled): void
    {
        self::put('enabled', $enabled);
    }

    /** Per-tool switch. A disabled tool 404s and leaves the header menu. */
    public static function toolEnabled(string $tool): bool
    {
        return (bool) (self::all()['tool_'.self::key($tool).'_enabled'] ?? true);
    }

    public static function setToolEnabled(string $tool, bool $enabled): void
    {
        self::put('tool_'.self::key($tool).'_enabled', $enabled);
    }

    /* ------------------------------------------------------------------ */
    /* Limits */
    /* ------------------------------------------------------------------ */

    /** Runs allowed per canonical email per calendar day. */
    public static function dailyPerEmail(): int
    {
        return (int) (self::all()['daily_per_email'] ?? config('tools.defaults.daily_per_email', 5));
    }

    /**
     * Runs allowed per device (hashed browser cookie) per calendar day. The
     * same allowance as the email quota by default: switching email address on
     * the same browser must not hand out a fresh set of free runs.
     */
    public static function dailyPerDevice(): int
    {
        return (int) (self::all()['daily_per_device'] ?? config('tools.defaults.daily_per_device', 5));
    }

    /** Submissions allowed per IP per minute. A burst guard, not a daily allowance. */
    public static function perIpPerMinute(): int
    {
        return (int) (self::all()['per_ip_per_minute'] ?? config('tools.defaults.per_ip_per_minute', 6));
    }

    /**
     * Runs allowed per IP per calendar day (0 = no ceiling). The email and
     * device ceilings both fall to someone willing to change both; this one is
     * what is left. Blunt by nature — an office, a campus or a mobile network
     * is one address for everyone behind it — so it is the ceiling most worth
     * setting high, or switching off, on a site with shared-network visitors.
     */
    public static function dailyPerIp(): int
    {
        return (int) (self::all()['daily_per_ip'] ?? config('tools.defaults.daily_per_ip', 5));
    }

    /** Site-wide ceiling on AI-processed runs per day (0 = unlimited). */
    public static function siteDailyCap(): int
    {
        return (int) (self::all()['site_daily_cap'] ?? config('tools.defaults.site_daily_cap', 300));
    }

    /** Hours an unopened result link stays alive. */
    public static function expiryHours(): int
    {
        return (int) (self::all()['expiry_hours'] ?? config('tools.defaults.expiry_hours', 72));
    }

    /** Hard retention: every run older than this is purged. */
    public static function retentionDays(): int
    {
        return (int) (self::all()['retention_days'] ?? config('tools.defaults.retention_days', 90));
    }

    public static function maxFileMb(): int
    {
        return (int) (self::all()['max_file_mb'] ?? config('tools.defaults.max_file_mb', 2));
    }

    public static function resendMax(): int
    {
        return (int) (self::all()['resend_max'] ?? config('tools.defaults.resend_max', 3));
    }

    /** Total resends one email address may trigger in a day, across all its runs. */
    public static function resendMaxPerEmail(): int
    {
        return (int) (self::all()['resend_max_per_email'] ?? config('tools.defaults.resend_max_per_email', 5));
    }

    /* ------------------------------------------------------------------ */
    /* Copy shown to visitors */
    /* ------------------------------------------------------------------ */

    /** The consent sentence beside the required checkbox on every tool form. */
    public static function consentText(): string
    {
        $value = trim((string) (self::all()['consent_text'] ?? ''));

        return $value !== '' ? $value : self::DEFAULT_CONSENT_TEXT;
    }

    public const DEFAULT_CONSENT_TEXT = 'I agree that the information I provide is processed by AI to produce this report.';

    public static function setConsentText(string $text): void
    {
        self::put('consent_text', trim($text));
    }

    /** Where "contact us" points from a refusal. Defaults to the contact page. */
    public static function contactUrl(): string
    {
        $value = trim((string) (self::all()['contact_url'] ?? ''));

        return $value !== '' ? $value : route('contact');
    }

    public static function setContactUrl(string $url): void
    {
        self::put('contact_url', trim($url));
    }

    /** From-name on the result email ('' = the app's own mail from-name). */
    public static function emailFromName(): string
    {
        return trim((string) (self::all()['email_from_name'] ?? ''));
    }

    public static function setEmailFromName(string $name): void
    {
        self::put('email_from_name', trim($name));
    }

    /** Subject override for one tool's result email ('' = the tool's own). */
    public static function emailSubject(string $tool): string
    {
        return trim((string) (self::all()['email_subject_'.self::key($tool)] ?? ''));
    }

    public static function setEmailSubject(string $tool, string $subject): void
    {
        self::put('email_subject_'.self::key($tool), trim($subject));
    }

    /* ------------------------------------------------------------------ */
    /* Behaviour toggles */
    /* ------------------------------------------------------------------ */

    /**
     * reCAPTCHA on the public tool forms. Shares the site keys with
     * registration and the contact form; on by default once those keys exist,
     * because a free AI form with no captcha is a magnet.
     */
    public static function recaptchaEnabled(): bool
    {
        $configured = SiteSettings::recaptchaSiteKey() !== '' && SiteSettings::recaptchaSecretKey() !== '';

        return $configured && (bool) (self::all()['recaptcha_enabled'] ?? true);
    }

    public static function setRecaptchaEnabled(bool $enabled): void
    {
        self::put('recaptcha_enabled', $enabled);
    }

    public static function storeRawPayloads(): bool
    {
        return (bool) (self::all()['store_raw_payloads'] ?? config('tools.defaults.store_raw_payloads', true));
    }

    /** Offer the uploaded CV for download on the public (shareable) result page. */
    public static function allowCvDownload(): bool
    {
        return (bool) (self::all()['allow_cv_download'] ?? config('tools.defaults.allow_cv_download', false));
    }

    public static function marketingOptIn(): bool
    {
        return (bool) (self::all()['marketing_opt_in'] ?? config('tools.defaults.marketing_opt_in', true));
    }

    public static function autoCleanupExpired(): bool
    {
        return (bool) (self::all()['auto_cleanup_expired'] ?? config('tools.defaults.auto_cleanup_expired', true));
    }

    public static function autoCleanupRetention(): bool
    {
        return (bool) (self::all()['auto_cleanup_retention'] ?? config('tools.defaults.auto_cleanup_retention', true));
    }

    /* ------------------------------------------------------------------ */
    /* Per-tool AI overrides (empty = inherit from AI Settings) */
    /* ------------------------------------------------------------------ */

    /** Configured provider for a tool's AI stage ('' = inherit). */
    public static function aiProvider(string $tool, string $stage): string
    {
        return trim((string) (self::all()[self::aiKey($tool, $stage, 'provider')] ?? ''));
    }

    public static function setAiProvider(string $tool, string $stage, string $provider): void
    {
        self::put(self::aiKey($tool, $stage, 'provider'), trim($provider));
    }

    /** Configured model for a tool's AI stage ('' = inherit). */
    public static function aiModel(string $tool, string $stage): string
    {
        return trim((string) (self::all()[self::aiKey($tool, $stage, 'model')] ?? ''));
    }

    public static function setAiModel(string $tool, string $stage, string $model): void
    {
        self::put(self::aiKey($tool, $stage, 'model'), trim($model));
    }

    /** Settings key for one tool/stage/field triple. */
    public static function aiKey(string $tool, string $stage, string $field): string
    {
        return 'ai_'.self::key($tool).'_'.self::key($stage).'_'.$field;
    }

    /* ------------------------------------------------------------------ */
    /* Blocklists */
    /* ------------------------------------------------------------------ */

    /** Extra banned email domains, merged with config/disposable_email.php. @return list<string> */
    public static function extraBannedDomains(): array
    {
        return self::lines('extra_banned_domains');
    }

    public static function setExtraBannedDomains(string $raw): void
    {
        self::put('extra_banned_domains', $raw);
    }

    /** @return list<string> */
    public static function blockedEmails(): array
    {
        return self::lines('blocked_emails');
    }

    public static function setBlockedEmails(string $raw): void
    {
        self::put('blocked_emails', $raw);
    }

    /** @return list<string> */
    public static function blockedIps(): array
    {
        return self::lines('blocked_ips');
    }

    public static function setBlockedIps(string $raw): void
    {
        self::put('blocked_ips', $raw);
    }

    /* ------------------------------------------------------------------ */
    /* SEO (per tool page) */
    /* ------------------------------------------------------------------ */

    public static function seoField(string $tool, string $field): string
    {
        return (string) (self::all()['seo_'.self::key($tool).'_'.$field] ?? '');
    }

    public static function setSeoField(string $tool, string $field, string $value): void
    {
        self::put('seo_'.self::key($tool).'_'.$field, $value);
    }

    /**
     * A tool page's meta, with the admin's overrides layered over the tool's
     * own defaults — an empty field always falls back rather than blanking out.
     *
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    public static function seoFor(string $tool, array $defaults = []): array
    {
        $meta = [];

        foreach (self::SEO_FIELDS as $field) {
            $meta[$field] = self::seoField($tool, $field) ?: (string) ($defaults[$field] ?? '');
        }

        return $meta;
    }

    /* ------------------------------------------------------------------ */
    /* Store */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $row = Setting::query()->where('key', self::SETTINGS_KEY)->first();

        return (array) ($row?->value ?? []);
    }

    public static function put(string $key, mixed $value): void
    {
        $values = self::all();
        $values[$key] = $value;

        Setting::query()->updateOrCreate(['key' => self::SETTINGS_KEY], ['value' => $values]);
    }

    /** Settings keys are built from slugs, so dashes become underscores. */
    private static function key(string $value): string
    {
        return str_replace('-', '_', mb_strtolower(trim($value)));
    }

    /** A textarea setting split into clean, lowercase, unique lines. @return list<string> */
    private static function lines(string $key): array
    {
        $raw = (string) (self::all()[$key] ?? '');

        $values = preg_split('/[\r\n,]+/', mb_strtolower($raw)) ?: [];
        $values = array_filter(array_map(fn ($v) => trim((string) $v, " \t\n\r\0\x0B@"), $values));

        return array_values(array_unique($values));
    }
}
