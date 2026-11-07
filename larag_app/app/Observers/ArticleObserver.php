<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\ArticleMedia;
use App\Services\Storage\ArticleStorage;
use App\Support\ArticleHtml;
use Illuminate\Support\Str;

/**
 * Keeps an article's derived fields honest, so the admin only has to fill in
 * the parts that need a human: title, body and (optionally) SEO.
 */
class ArticleObserver
{
    public function __construct(private readonly ArticleStorage $storage) {}

    public function saving(Article $article): void
    {
        $article->slug = $this->slugFor($article);
        $article->custom_css = ArticleHtml::sanitizeCss($article->custom_css) ?: null;

        $body = $article->content_mode === Article::MODE_HTML
            ? $article->content_html
            : $article->content_editor;

        // Blank excerpt: derive one rather than ship an empty <description>
        // into the feed and an empty meta description into the page.
        if (trim((string) $article->excerpt) === '') {
            $text = ArticleHtml::plainText($body);
            $article->excerpt = $text === '' ? null : Str::limit($text, 300);
        }

        $article->reading_minutes = trim((string) $body) === ''
            ? null
            : ArticleHtml::readingMinutes($body);

        // The form uploads a cover path; the disk it landed on is recorded here
        // so a later switch to S3 leaves existing covers resolvable.
        if (blank($article->cover_path)) {
            $article->cover_disk = null;
            $article->cover_alt = $article->cover_alt ?: null;
        } elseif (blank($article->cover_disk)) {
            $article->cover_disk = $this->storage->disk();
        }

        // Publishing without picking a date means "now", not "never".
        if ($article->status === Article::STATUS_PUBLISHED && $article->published_at === null) {
            $article->published_at = now();
        }
    }

    /** Every article gets its SEO row, so the form can always bind to it. */
    public function created(Article $article): void
    {
        if (! $article->meta()->exists()) {
            $article->meta()->create([]);
        }
    }

    /**
     * Swapping the cover leaves the old file behind — Filament's FileUpload
     * only cleans up after itself in the Spatie variant — so ten swaps would
     * leak ten files.
     */
    public function updating(Article $article): void
    {
        if (! $article->isDirty('cover_path')) {
            return;
        }

        $previous = (string) $article->getOriginal('cover_path');

        if ($previous !== '' && $previous !== $article->cover_path) {
            $this->storage->delete((string) $article->getOriginal('cover_disk'), $previous);
        }
    }

    /**
     * Unlink the files BEFORE the row goes.
     *
     * article_media.article_id cascades at the database level, so once the
     * delete runs those rows are gone and PHP never sees them — the files
     * would be orphaned on disk forever. A soft delete is left alone: the
     * article can still be restored, images and all.
     */
    public function deleting(Article $article): void
    {
        if (! $article->isForceDeleting()) {
            return;
        }

        $article->media()->each(function (ArticleMedia $media): void {
            $this->storage->delete((string) $media->disk, (string) $media->path);
        });

        $this->storage->delete((string) $article->cover_disk, (string) $article->cover_path);
    }

    /**
     * A slug is normally typed in the form, but it must never be empty, never
     * collide with a literal segment under /blog, and never be the empty
     * string Str::slug() returns for a fully non-latin title.
     */
    private function slugFor(Article $article): string
    {
        $slug = Str::slug((string) $article->slug ?: (string) $article->title);

        if ($slug === '') {
            $slug = 'article-'.Str::lower(Str::random(8));
        }

        if (in_array($slug, Article::RESERVED_SLUGS, true)) {
            $slug = $slug.'-post';
        }

        return $slug;
    }
}
