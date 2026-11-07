<?php

namespace App\Support;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Builds the meta array that <x-site.seo> renders, for blog pages.
 *
 * Everything falls back rather than blanking out: a field left empty on the
 * article drops to the article itself, then to the Blog Settings defaults, so
 * a post published without ever opening the SEO tab still has sane tags.
 */
class ArticleSeo
{
    public static function forArticle(Article $article): array
    {
        $meta = $article->meta;

        $title = self::withSuffix(self::firstFilled([$meta?->meta_title, $article->title]));
        $description = self::firstFilled([$meta?->meta_description, $article->excerpt, BlogSettings::defaultMetaDescription()]);
        $image = self::image([$meta?->og_image, $article->coverUrl(), BlogSettings::defaultOgImage()]);

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => (string) ($meta?->meta_keywords ?? ''),
            'image' => $image,
            'type' => 'article',
            // A canonical pointing elsewhere is how a republished piece credits
            // the original, so it wins over this site's own URL when set.
            'canonical' => self::firstFilled([$meta?->canonical_url, route('blog.show', $article->slug)]),
            // Only an admin can load a draft, but say so in the markup too —
            // the X-Robots-Tag header covers crawlers that never read the HTML.
            'robots' => $article->isPublished()
                ? self::firstFilled([$meta?->robots, BlogSettings::defaultRobots()])
                : 'noindex, nofollow',
            'twitter_card' => self::firstFilled([$meta?->twitter_card, 'summary_large_image']),
            'og_title' => self::firstFilled([$meta?->og_title, $title]),
            'og_description' => self::firstFilled([$meta?->og_description, $description]),
            'published_time' => $article->published_at?->toAtomString() ?? '',
            'modified_time' => $article->updated_at?->toAtomString() ?? '',
            'section' => (string) ($article->category?->name ?? ''),
        ];
    }

    /** The index and the category archives share a shape; one is filtered. */
    public static function forListing(LengthAwarePaginator $articles, ?ArticleCategory $category = null): array
    {
        $base = $category !== null
            ? route('blog.category', $category->slug)
            : route('blog.index');

        $page = max(1, (int) $articles->currentPage());

        $name = $category !== null ? $category->name : BlogSettings::blogTitle();
        $description = self::firstFilled([
            $category?->description,
            $category !== null ? null : BlogSettings::blogIntro(),
            BlogSettings::defaultMetaDescription(),
        ]);

        return [
            // Page 2 needs a title of its own, or every page of the archive is
            // a duplicate of page 1 as far as a search engine is concerned.
            'title' => self::withSuffix($page > 1 ? $name.' — page '.$page : $name),
            'description' => $description,
            'keywords' => '',
            'image' => self::image([BlogSettings::defaultOgImage()]),
            'type' => 'website',
            // Self-referencing, INCLUDING the page number. Canonicalising page 2
            // to page 1 reads as a soft duplicate and can drop the older posts.
            'canonical' => $page > 1 ? $base.'?page='.$page : $base,
            'robots' => BlogSettings::defaultRobots(),
            'twitter_card' => 'summary_large_image',
        ];
    }

    /**
     * schema.org data for the article page. Rendered as JSON-LD, which is what
     * search engines actually read for article metadata.
     */
    public static function jsonLd(Article $article): array
    {
        $meta = $article->meta;
        $seo = self::forArticle($article);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => self::firstFilled([$meta?->schema_type, 'BlogPosting']),
            'headline' => self::firstFilled([$meta?->meta_title, $article->title]),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $seo['canonical']],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'Hiremium'),
                'logo' => ['@type' => 'ImageObject', 'url' => asset('logo.svg')],
            ],
        ];

        if ($seo['description'] !== '') {
            $data['description'] = $seo['description'];
        }

        if ($seo['image'] !== '') {
            $data['image'] = [$seo['image']];
        }

        if ($article->published_at) {
            $data['datePublished'] = $article->published_at->toAtomString();
        }

        if ($article->updated_at) {
            $data['dateModified'] = $article->updated_at->toAtomString();
        }

        if (filled($article->author_name)) {
            $data['author'] = ['@type' => 'Person', 'name' => $article->author_name];
        }

        if ($article->category) {
            $data['articleSection'] = $article->category->name;
        }

        return $data;
    }

    /** The first value that is not blank, as a trimmed string. */
    private static function firstFilled(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** Append the site's title suffix, unless the title already carries it. */
    private static function withSuffix(string $title): string
    {
        $suffix = BlogSettings::metaTitleSuffix();

        if ($suffix === '') {
            $suffix = ' — '.config('app.name', 'Hiremium');
        }

        return str_ends_with($title, trim($suffix)) ? $title : $title.$suffix;
    }

    /**
     * A share image must be an absolute URL — Open Graph rejects relative ones,
     * and this app is installed in a subdirectory, so "/media/..." would resolve
     * against the wrong root.
     */
    private static function image(array $candidates): string
    {
        $image = self::firstFilled($candidates);

        if ($image === '') {
            return asset('images/og-default.png');
        }

        return str_starts_with($image, 'http://') || str_starts_with($image, 'https://')
            ? $image
            : url($image);
    }
}
