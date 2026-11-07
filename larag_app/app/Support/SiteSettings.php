<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Global site toggles managed by the admin (Filament → Site Settings). Stored as a single
 * key/value JSON row so future switches live alongside these.
 */
class SiteSettings
{
    public const SETTINGS_KEY = 'site_settings';

    /** Supported social login providers (public URL slug => label). */
    public const SOCIAL_PROVIDERS = ['google', 'facebook', 'linkedin'];

    /** Public pages with editable SEO meta (page key => admin label). */
    public const SEO_PAGES = [
        'home' => 'Home',
        'how_it_works' => 'How it works',
        'features_matching' => 'AI Match Scoring',
        'features_parsing' => 'AI Resume Parsing',
        'docs' => 'API Docs',
        'pricing' => 'Pricing',
        'help' => 'Help',
        'about' => 'About',
        'contact' => 'Contact',
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Service',
        'refund' => 'Refund Policy',
    ];

    /** Route name => SEO page key. */
    public const SEO_ROUTES = [
        'home' => 'home',
        'how-it-works' => 'how_it_works',
        'features.matching' => 'features_matching',
        'features.parsing' => 'features_parsing',
        'docs' => 'docs',
        'pricing' => 'pricing',
        'help' => 'help',
        'about' => 'about',
        'contact' => 'contact',
        'privacy' => 'privacy',
        'terms' => 'terms',
        'refund' => 'refund',
    ];

    /** SEO fields stored per page. */
    public const SEO_FIELDS = ['title', 'description', 'keywords', 'image'];

    /** Whether new account sign-ups are accepted. Defaults to open. */
    public static function registrationEnabled(): bool
    {
        return (bool) (self::all()['registration_enabled'] ?? true);
    }

    public static function setRegistrationEnabled(bool $enabled): void
    {
        self::put('registration_enabled', $enabled);
    }

    /**
     * Whether outgoing HTTPS requests to AI providers verify the SSL
     * certificate. Defaults to ON (secure). Turn OFF only for local
     * development behind a self-signed / intercepting certificate.
     */
    public static function sslVerifyEnabled(): bool
    {
        return (bool) (self::all()['ssl_verify_enabled'] ?? true);
    }

    public static function setSslVerifyEnabled(bool $enabled): void
    {
        self::put('ssl_verify_enabled', $enabled);
    }

    /**
     * Testing mode: the CV pipeline (bulk upload parse + match, both lanes)
     * skips the real AI providers and produces fake parse data, a random score
     * (30–95) and canned reasons — so the whole flow can be exercised without
     * spending tokens. Defaults to OFF.
     */
    public static function testingModeEnabled(): bool
    {
        return (bool) (self::all()['testing_mode_enabled'] ?? false);
    }

    public static function setTestingModeEnabled(bool $enabled): void
    {
        self::put('testing_mode_enabled', $enabled);
    }

    /** Whether Google reCAPTCHA v2 is enforced on the register form. Defaults to off. */
    public static function recaptchaEnabled(): bool
    {
        return (bool) (self::all()['recaptcha_enabled'] ?? false)
            && self::recaptchaSiteKey() !== ''
            && self::recaptchaSecretKey() !== '';
    }

    public static function setRecaptchaEnabled(bool $enabled): void
    {
        self::put('recaptcha_enabled', $enabled);
    }

    public static function recaptchaSiteKey(): string
    {
        return (string) (self::all()['recaptcha_site_key'] ?? '');
    }

    public static function setRecaptchaSiteKey(string $key): void
    {
        self::put('recaptcha_site_key', trim($key));
    }

    /** Secret key is encrypted at rest; decrypt failures return an empty string. */
    public static function recaptchaSecretKey(): string
    {
        $encrypted = (string) (self::all()['recaptcha_secret_key'] ?? '');

        if ($encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return '';
        }
    }

    public static function setRecaptchaSecretKey(string $key): void
    {
        $key = trim($key);
        self::put('recaptcha_secret_key', $key === '' ? '' : Crypt::encryptString($key));
    }

    /**
     * Whether reCAPTCHA is enforced on the contact form. Reuses the same
     * reCAPTCHA site/secret keys as registration.
     */
    public static function contactRecaptchaEnabled(): bool
    {
        return (bool) (self::all()['contact_recaptcha_enabled'] ?? false)
            && self::recaptchaSiteKey() !== ''
            && self::recaptchaSecretKey() !== '';
    }

    public static function setContactRecaptchaEnabled(bool $enabled): void
    {
        self::put('contact_recaptcha_enabled', $enabled);
    }

    /**
     * Address contact-form submissions are emailed to. Falls back to the
     * configured mail "from" address when not set.
     */
    public static function contactSendToEmail(): string
    {
        $email = trim((string) (self::all()['contact_send_to_email'] ?? ''));

        return $email !== '' ? $email : (string) config('mail.from.address');
    }

    public static function setContactSendToEmail(string $email): void
    {
        self::put('contact_send_to_email', trim($email));
    }

    /**
     * Whether a social provider is enabled AND fully configured.
     */
    public static function socialEnabled(string $provider): bool
    {
        if (! in_array($provider, self::SOCIAL_PROVIDERS, true)) {
            return false;
        }

        return (bool) (self::all()["social_{$provider}_enabled"] ?? false)
            && self::socialClientId($provider) !== ''
            && self::socialClientSecret($provider) !== '';
    }

    public static function setSocialEnabled(string $provider, bool $enabled): void
    {
        self::put("social_{$provider}_enabled", $enabled);
    }

    public static function socialClientId(string $provider): string
    {
        return (string) (self::all()["social_{$provider}_client_id"] ?? '');
    }

    public static function setSocialClientId(string $provider, string $clientId): void
    {
        self::put("social_{$provider}_client_id", trim($clientId));
    }

    /** Client secret is encrypted at rest; decrypt failures return an empty string. */
    public static function socialClientSecret(string $provider): string
    {
        $encrypted = (string) (self::all()["social_{$provider}_client_secret"] ?? '');

        if ($encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return '';
        }
    }

    public static function setSocialClientSecret(string $provider, string $secret): void
    {
        $secret = trim($secret);
        self::put("social_{$provider}_client_secret", $secret === '' ? '' : Crypt::encryptString($secret));
    }

    /** @return list<string> Providers that are toggled on and fully configured. */
    public static function enabledSocialProviders(): array
    {
        return array_values(array_filter(
            self::SOCIAL_PROVIDERS,
            fn (string $provider) => self::socialEnabled($provider),
        ));
    }

    /**
     * Content-derived default meta per page, used whenever the admin
     * has not overridden a field in Site Settings → SEO.
     *
     * @return array<string, array{title: string, description: string, keywords: string, image: string}>
     */
    public static function seoDefaults(): array
    {
        return [
            'home' => [
                'title' => 'Hiremium — Bulk CV Screening & AI Candidate Matching in Minutes',
                'description' => 'Upload CVs in bulk and get a ranked shortlist of your best-matched candidates in minutes. Automatic screening with a match score and report for every applicant.',
                'keywords' => 'bulk CV screening, AI candidate matching, resume screening software, automated recruitment, candidate shortlisting, applicant ranking',
                'image' => '',
            ],
            'how_it_works' => [
                'title' => 'How It Works — Bulk CV Screening in Minutes | Hiremium',
                'description' => 'Post a job, upload your applicants in bulk, and let AI read, analyze and score each CV against the role. You come back to a ranked shortlist with a report per candidate.',
                'keywords' => 'how it works, bulk resume screening, AI candidate scoring, automated shortlisting, recruitment workflow',
                'image' => '',
            ],
            'features_matching' => [
                'title' => 'AI Match Scoring — Rank Every Candidate | Hiremium',
                'description' => 'Every candidate is scored against your job with a clear match score and the reasons behind it — strengths, concerns, and the required skills they have and are missing.',
                'keywords' => 'AI match scoring, candidate ranking, application screening, candidate matching, resume scoring',
                'image' => '',
            ],
            'features_parsing' => [
                'title' => 'AI Resume Parsing — Any CV to a Clean Profile | Hiremium',
                'description' => 'Every resume — PDF, Word, plain text, scanned or a photo — is read into a clean candidate profile: experience, education, projects, skills and languages.',
                'keywords' => 'resume parsing, CV parser, resume data extraction, scanned resume reading, candidate profiles',
                'image' => '',
            ],
            'docs' => [
                'title' => 'API Documentation — Hiremium',
                'description' => 'Full reference for the Hiremium API: authentication, AI usage, rate limits, errors, and copy-paste examples in cURL, PHP, Node.js and Python.',
                'keywords' => 'Hiremium API docs, resume parsing API reference, match scoring API reference, REST API documentation',
                'image' => '',
            ],
            'pricing' => [
                'title' => 'Pricing — Plans for Every Hiring Volume | Hiremium',
                'description' => 'Simple plans that scale with your hiring. Pay for AI usage, nothing else — every plan includes bulk upload, automatic match scoring, and a report per candidate.',
                'keywords' => 'recruitment software pricing, CV screening pricing, AI hiring plans, candidate matching pricing',
                'image' => '',
            ],
            'about' => [
                'title' => 'About Hiremium — Automated Screening for Hiring',
                'description' => 'Why we built Hiremium: fast, explainable first-pass screening for your applicants, with usage-based pricing and a report behind every candidate.',
                'keywords' => 'about Hiremium, AI hiring, recruitment automation, applicant screening',
                'image' => '',
            ],
            'contact' => [
                'title' => 'Contact Us — Hiremium',
                'description' => 'Questions about plans, integration help or custom volumes? Get in touch with the Hiremium team — we reply within one business day.',
                'keywords' => 'contact Hiremium, API support, sales, custom plan',
                'image' => '',
            ],
            'help' => [
                'title' => 'Help Center — Hiremium',
                'description' => 'Guides and answers for using Hiremium: your dashboard workflow, bulk CV upload, candidate scoring and reports, AI usage, plans, upgrades, cancellations, the developer API and more.',
                'keywords' => 'Hiremium help, dashboard guide, bulk upload help, AI usage, plan upgrade, cancellation, API help, troubleshooting',
                'image' => '',
            ],
            'privacy' => [
                'title' => 'Privacy Policy — Hiremium',
                'description' => 'How Hiremium collects, uses, stores and protects your data and the resume/job content processed by our AI APIs.',
                'keywords' => 'Hiremium privacy policy, data protection, GDPR, data handling',
                'image' => '',
            ],
            'terms' => [
                'title' => 'Terms of Service — Hiremium',
                'description' => 'The terms that govern your use of the Hiremium website and AI features — accounts, AI usage, billing, acceptable use, and liability.',
                'keywords' => 'Hiremium terms of service, terms and conditions, acceptable use',
                'image' => '',
            ],
            'refund' => [
                'title' => 'Refund Policy — Hiremium',
                'description' => 'Every failed AI request is refunded to your balance automatically. Subscription fees are not refundable — the Free plan lets you test the full product before you buy.',
                'keywords' => 'Hiremium refund policy, failed request refund, subscription refund, cancellation',
                'image' => '',
            ],
        ];
    }

    /**
     * Effective SEO meta for a page: admin overrides win field-by-field,
     * empty fields fall back to the content-derived defaults.
     *
     * @return array{title: string, description: string, keywords: string, image: string}|null
     */
    public static function seoFor(string $page): ?array
    {
        $defaults = self::seoDefaults()[$page] ?? null;

        if ($defaults === null) {
            return null;
        }

        $stored = (array) (self::all()['seo'][$page] ?? []);

        $seo = [];
        foreach (self::SEO_FIELDS as $field) {
            $value = trim((string) ($stored[$field] ?? ''));
            $seo[$field] = $value !== '' ? $value : $defaults[$field];
        }

        return $seo;
    }

    /** Effective SEO meta for a route name, or null for non-SEO pages. */
    public static function seoForRoute(?string $routeName): ?array
    {
        $page = self::SEO_ROUTES[$routeName ?? ''] ?? null;

        return $page !== null ? self::seoFor($page) : null;
    }

    /**
     * Raw admin overrides for a page (empty string = "use default").
     *
     * @return array{title: string, description: string, keywords: string, image: string}
     */
    public static function seoOverrides(string $page): array
    {
        $stored = (array) (self::all()['seo'][$page] ?? []);

        $overrides = [];
        foreach (self::SEO_FIELDS as $field) {
            $overrides[$field] = trim((string) ($stored[$field] ?? ''));
        }

        return $overrides;
    }

    /** Persist admin SEO overrides for one page. */
    public static function setSeo(string $page, array $fields): void
    {
        if (! array_key_exists($page, self::SEO_PAGES)) {
            return;
        }

        $seo = (array) (self::all()['seo'] ?? []);

        $clean = [];
        foreach (self::SEO_FIELDS as $field) {
            $clean[$field] = trim((string) ($fields[$field] ?? ''));
        }

        $seo[$page] = $clean;

        self::put('seo', $seo);
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return (array) (Setting::where('key', self::SETTINGS_KEY)->first()?->value ?? []);
    }

    public static function put(string $key, mixed $value): void
    {
        $settings = self::all();
        $settings[$key] = $value;

        Setting::updateOrCreate(['key' => self::SETTINGS_KEY], ['value' => $settings]);
    }
}
