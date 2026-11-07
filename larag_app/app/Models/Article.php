<?php

namespace App\Models;

use App\Support\ArticleHtml;
use App\Support\BlogSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A blog post.
 *
 * The body is stored twice on purpose — `content_editor` for the rich editor
 * and `content_html` for markup pasted by hand — because the rich editor
 * rewrites anything it does not recognise. Keeping both means switching
 * `content_mode` back and forth never destroys either version.
 */
class Article extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Body written in the rich editor. */
    public const MODE_EDITOR = 'editor';

    /** Body pasted as raw HTML and rendered exactly as given. */
    public const MODE_HTML = 'html';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * Slugs that would collide with a literal segment under /blog. Guarded in
     * the observer rather than by a database rule so the message is readable.
     */
    public const RESERVED_SLUGS = ['feed', 'feed.xml', 'category', 'page', 'create', 'edit'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reading_minutes' => 'integer',
        ];
    }

    /**
     * Live posts only. Deliberately a LOCAL scope: a global scope would hide
     * drafts from the admin panel, which is where they are written.
     *
     * A `published_at` in the future keeps the post hidden until that moment
     * arrives, so scheduling costs nothing and needs no cron.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    /** The body to render, chosen by the article's mode. */
    public function body(): string
    {
        return ArticleHtml::body($this);
    }

    /** Absolute URL of the cover image, or null when there is none. */
    public function coverUrl(): ?string
    {
        if (blank($this->cover_disk) || blank($this->cover_path)) {
            return null;
        }

        return BlogSettings::mediaUrl((string) $this->cover_disk, (string) $this->cover_path);
    }

    public function meta(): HasOne
    {
        return $this->hasOne(ArticleMeta::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ArticleMedia::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }
}
