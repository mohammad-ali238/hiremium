<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

/**
 * Everything that governs the blog (Filament → Content → Blog Settings).
 * Stored as one key/value JSON row, exactly like SiteSettings and ToolSettings.
 *
 * The module is deliberately self-contained: nothing it needs lives in .env or
 * in a config file, so the blog can be configured, moved to S3 and switched off
 * entirely from the admin panel, and removing it means dropping four tables and
 * this one settings row.
 *
 * Every getter has a working default, so the blog behaves sanely before an
 * admin has ever opened the settings page.
 */
class BlogSettings
{
    public const SETTINGS_KEY = 'blog_settings';

    /* ------------------------------------------------------------------ */
    /* General */
    /* ------------------------------------------------------------------ */

    /**
     * Master switch. Off: /blog and every article 404, the header link
     * disappears, and no article reaches the sitemap or the feed.
     */
    public static function enabled(): bool
    {
        return (bool) (self::all()['enabled'] ?? true);
    }

    public static function setEnabled(bool $enabled): void
    {
        self::put('enabled', $enabled);
    }

    public static function postsPerPage(): int
    {
        return max(1, (int) (self::all()['posts_per_page'] ?? 12));
    }

    public static function blogTitle(): string
    {
        return self::string('blog_title') ?: 'Blog';
    }

    public static function blogIntro(): string
    {
        return self::string('blog_intro');
    }

    /** Show the byline on article pages. */
    public static function showAuthor(): bool
    {
        return (bool) (self::all()['show_author'] ?? true);
    }

    public static function showReadingTime(): bool
    {
        return (bool) (self::all()['show_reading_time'] ?? true);
    }

    /* ------------------------------------------------------------------ */
    /* Content & editor */
    /* ------------------------------------------------------------------ */

    /**
     * Whether pasted HTML is cleaned before it reaches the page.
     *
     * OFF by default and that is deliberate: HTML mode exists so embeds
     * (video, forms, widgets) work, and those are scripts. The stored column
     * is never touched either way — this only changes what is rendered — so
     * turning it on is reversible. Turn it on the moment anyone other than a
     * trusted admin can write articles.
     */
    public static function sanitizeHtml(): bool
    {
        return (bool) (self::all()['sanitize_html'] ?? false);
    }

    public static function allowCustomCss(): bool
    {
        return (bool) (self::all()['allow_custom_css'] ?? true);
    }

    /** Ceiling on the per-article CSS box, in bytes. */
    public static function cssMaxBytes(): int
    {
        return max(256, (int) (self::all()['css_max_bytes'] ?? 8192));
    }

    /** Which editor a brand new article opens in. */
    public static function defaultContentMode(): string
    {
        $mode = self::string('default_content_mode');

        return $mode !== '' ? $mode : 'editor';
    }

    /* ------------------------------------------------------------------ */
    /* SEO defaults — the last fallback behind article_meta and the article */
    /* ------------------------------------------------------------------ */

    /**
     * Appended to every article title, e.g. ' — Hiremium'. Deliberately NOT
     * trimmed: the leading space is part of the separator the admin chose.
     */
    public static function metaTitleSuffix(): string
    {
        return (string) (self::all()['meta_title_suffix'] ?? '');
    }

    public static function defaultMetaDescription(): string
    {
        return self::string('default_meta_description');
    }

    public static function defaultOgImage(): string
    {
        return self::string('default_og_image');
    }

    public static function defaultRobots(): string
    {
        return self::string('default_robots') ?: 'index,follow';
    }

    /* ------------------------------------------------------------------ */
    /* Feed */

    public static function feedEnabled(): bool
    {
        return (bool) (self::all()['feed_enabled'] ?? true);
    }

    public static function feedTitle(): string
    {
        return self::string('feed_title') ?: self::blogTitle().' — '.config('app.name', 'Hiremium');
    }

    public static function feedDescription(): string
    {
        return self::string('feed_description') ?: (self::blogIntro() ?: self::defaultMetaDescription());
    }

    /** How many of the newest articles the feed carries. */
    public static function feedLimit(): int
    {
        return max(1, min(200, (int) (self::all()['feed_limit'] ?? 20)));
    }

    /* Media & storage */
    /* ------------------------------------------------------------------ */

    /** Local disk name. Stable across a driver switch, so old rows keep resolving. */
    public const DISK_LOCAL = 'article_local';

    public const DISK_S3 = 'article_s3';

    /** Uploads accepted by default. SVG is absent on purpose — see DENIED_EXTENSIONS. */
    public const DEFAULT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /**
     * Never allowed, whatever an admin types in the settings box.
     *
     * The media folder lives inside the web root and is served directly, so an
     * SVG carrying an inline <script> would be same-origin stored XSS that
     * never passes through any HTML handling. PHP-ish extensions are listed
     * for the same reason.
     */
    public const DENIED_EXTENSIONS = ['svg', 'svgz', 'html', 'htm', 'xml', 'php', 'php3', 'php4', 'php5', 'phtml', 'phar'];

    /** 'local' or 's3' — what the admin asked for, which may not be usable. */
    public static function storageDriver(): string
    {
        return self::string('storage_driver') === 's3' ? 's3' : 'local';
    }

    /**
     * Whether an S3 disk could actually be built. The blog stores its
     * credentials in the database, but it cannot install a composer package —
     * without the Flysystem S3 adapter the driver would throw on first use.
     */
    public static function s3Available(): bool
    {
        return class_exists(AwsS3V3Adapter::class);
    }

    /** The disk uploads go to now. Falls back to local rather than throwing. */
    public static function storageDisk(): string
    {
        return self::storageDriver() === 's3' && self::s3Available()
            ? self::DISK_S3
            : self::DISK_LOCAL;
    }

    /**
     * Where local media is written. Defaults inside the public web root, which
     * on this install is the docroot itself — so no storage:link is involved
     * and the files are served straight by the web server.
     *
     * The disk is rooted at the media folder, not at media/articles: every key
     * already starts with "articles/", which keeps an S3 bucket namespaced if
     * it is ever shared with something else.
     */
    public static function localRoot(): string
    {
        return self::string('local_root') ?: public_path('media');
    }

    /** Public base URL for local media. Must be absolute — see mediaUrl(). */
    public static function localUrl(): string
    {
        return self::string('local_url') ?: rtrim((string) config('app.url'), '/').'/media';
    }

    public static function maxImageMb(): int
    {
        return max(1, (int) (self::all()['max_image_mb'] ?? 5));
    }

    /** @return list<string> Lowercase extensions, with the dangerous ones removed. */
    public static function allowedExtensions(): array
    {
        $raw = self::string('allowed_ext');

        $values = $raw === ''
            ? self::DEFAULT_EXTENSIONS
            : (preg_split('/[\s,]+/', mb_strtolower($raw)) ?: []);

        $values = array_filter(array_map(fn ($v) => ltrim(trim((string) $v), '.'), $values));
        $values = array_diff($values, self::DENIED_EXTENSIONS);

        return array_values(array_unique($values)) ?: self::DEFAULT_EXTENSIONS;
    }

    /* --- S3 credentials. Secrets are encrypted at rest, like SiteSettings. --- */

    public static function s3Key(): string
    {
        return self::decrypt('s3_key');
    }

    public static function setS3Key(string $key): void
    {
        self::encrypt('s3_key', $key);
    }

    public static function s3Secret(): string
    {
        return self::decrypt('s3_secret');
    }

    public static function setS3Secret(string $secret): void
    {
        self::encrypt('s3_secret', $secret);
    }

    public static function s3Region(): string
    {
        return self::string('s3_region');
    }

    public static function s3Bucket(): string
    {
        return self::string('s3_bucket');
    }

    public static function s3Endpoint(): string
    {
        return self::string('s3_endpoint');
    }

    /** Public base URL of the bucket (or its CDN domain). */
    public static function s3Url(): string
    {
        return self::string('s3_url');
    }

    public static function s3PathStyle(): bool
    {
        return (bool) (self::all()['s3_path_style'] ?? false);
    }

    /**
     * Build the blog's two disks from the database and hand them to Laravel.
     *
     * This is what keeps config/filesystems.php untouched: config() writes are
     * per-request and in-memory, and Storage resolves disks lazily afterwards,
     * so Filament's uploads, the media panel and the public page all get a
     * normal named disk whose settings happen to live in a settings row.
     *
     * Called lazily rather than from a provider boot, so requests that have
     * nothing to do with the blog never pay for the extra query.
     */
    public static function registerDisks(): void
    {
        if (self::$disksRegistered) {
            return;
        }

        self::$disksRegistered = true;

        config([
            'filesystems.disks.'.self::DISK_LOCAL => [
                'driver' => 'local',
                'root' => self::localRoot(),
                'url' => self::localUrl(),
                'visibility' => 'public',
                'throw' => false,
            ],
            'filesystems.disks.'.self::DISK_S3 => [
                'driver' => 's3',
                'key' => self::s3Key(),
                'secret' => self::s3Secret(),
                'region' => self::s3Region(),
                'bucket' => self::s3Bucket(),
                'endpoint' => self::s3Endpoint() ?: null,
                'url' => self::s3Url() ?: null,
                'use_path_style_endpoint' => self::s3PathStyle(),
                // Article media is read straight from the browser, so unlike the
                // private CV bucket this one has to be public-read.
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);
    }

    /** Drop the built disks so the next call rebuilds them from current settings. */
    public static function forgetDisks(): void
    {
        self::$disksRegistered = false;

        Storage::forgetDisk([self::DISK_LOCAL, self::DISK_S3]);
    }

    /**
     * Absolute public URL for a stored file.
     *
     * Absolute, always: this app is installed in a subdirectory, so a
     * root-relative "/media/..." pasted into an article would resolve against
     * the domain root and 404.
     */
    public static function mediaUrl(string $disk, string $path): string
    {
        self::registerDisks();

        $url = Storage::disk($disk)->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    /* ------------------------------------------------------------------ */
    /* Store */
    /* ------------------------------------------------------------------ */

    private static bool $disksRegistered = false;

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

        // A saved setting may have changed where media lives, so any disk built
        // from the old values has to go.
        self::forgetDisks();
    }

    /** A trimmed string setting; '' when unset. */
    private static function string(string $key): string
    {
        return trim((string) (self::all()[$key] ?? ''));
    }

    /** Read a secret stored encrypted; an undecryptable value reads as empty. */
    private static function decrypt(string $key): string
    {
        $encrypted = (string) (self::all()[$key] ?? '');

        if ($encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return '';
        }
    }

    private static function encrypt(string $key, string $value): void
    {
        $value = trim($value);

        self::put($key, $value === '' ? '' : Crypt::encryptString($value));
    }
}
