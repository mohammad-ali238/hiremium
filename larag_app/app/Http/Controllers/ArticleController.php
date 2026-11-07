<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Support\ArticleSeo;
use App\Support\BlogSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * The public blog: index, category archive and the article page.
 *
 * A plain controller rather than Livewire, on purpose. Article bodies can be
 * hand-pasted HTML — duplicate ids, stray attributes, embedded scripts — and
 * Livewire's DOM morphing does not survive that. None of these pages needs
 * reactivity either.
 *
 * Slugs are looked up here rather than by route-model binding: Filament falls
 * back to a model's route key, so a slug key would rewrite the admin URLs and
 * break the redirect after a slug is edited.
 */
class ArticleController extends Controller
{
    /** Columns the listing needs. Never the two longText body columns. */
    private const LIST_COLUMNS = [
        'id', 'slug', 'title', 'excerpt', 'article_category_id',
        'cover_disk', 'cover_path', 'cover_alt',
        'author_name', 'published_at', 'reading_minutes',
    ];

    public function index(): View
    {
        $this->assertBlogIsOn();

        $articles = $this->listing()->paginate(BlogSettings::postsPerPage())->withQueryString();

        return view('blog.index', [
            'title' => BlogSettings::blogTitle(),
            'articles' => $articles,
            'categories' => $this->categoriesWithPosts(),
            'category' => null,
            'seo' => ArticleSeo::forListing($articles),
        ]);
    }

    public function category(string $slug): View
    {
        $this->assertBlogIsOn();

        $category = ArticleCategory::where('slug', $slug)->firstOrFail();

        $articles = $this->listing()
            ->where('article_category_id', $category->id)
            ->paginate(BlogSettings::postsPerPage())
            ->withQueryString();

        // Same view as the index: an archive is the index with one filter on.
        return view('blog.index', [
            'title' => $category->name,
            'category' => $category,
            'articles' => $articles,
            'categories' => $this->categoriesWithPosts(),
            'seo' => ArticleSeo::forListing($articles, $category),
        ]);
    }

    public function show(string $slug): View|Response
    {
        $this->assertBlogIsOn();

        // Soft-deleted articles are excluded by the model's global scope, so a
        // deleted post is gone for everyone, admins included.
        $article = Article::with(['meta', 'category'])->where('slug', $slug)->first();

        abort_if($article === null, 404);

        $data = [
            'title' => $article->title,
            'article' => $article,
            'isPreview' => false,
            'seo' => ArticleSeo::forArticle($article),
        ];

        if ($article->isPublished()) {
            return view('blog.show', $data);
        }

        // Drafts and scheduled posts: an admin sees exactly what will publish,
        // everyone else sees a 404. No tokens, no separate preview route.
        abort_unless((bool) request()->user()?->is_admin, 404);

        $data['isPreview'] = true;

        return response()
            ->view('blog.show', $data)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * RSS 2.0: each article's summary and a link, never the body.
     *
     * That decision is what keeps the feed valid and small — hand-pasted
     * article HTML is exactly the kind of markup that breaks feed readers —
     * and it brings subscribers to the page, where an article's own CSS
     * actually applies. Per-article CSS is never published here.
     */
    public function feed(): Response
    {
        $this->assertBlogIsOn();

        abort_unless(BlogSettings::feedEnabled(), 404);

        $articles = Article::published()
            ->select(self::LIST_COLUMNS)
            ->with('category:id,name,slug')
            ->latest('published_at')
            ->limit(BlogSettings::feedLimit())
            ->get();

        $built = $articles->first()?->published_at ?? now();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
        $xml .= "<channel>\n";
        $xml .= '  <title>'.self::xmlText(BlogSettings::feedTitle())."</title>\n";
        $xml .= '  <link>'.self::xmlText(route('blog.index'))."</link>\n";
        $xml .= '  <description>'.self::xmlText(BlogSettings::feedDescription())."</description>\n";
        $xml .= '  <language>'.self::xmlText(str_replace('_', '-', app()->getLocale()))."</language>\n";
        // RFC-2822, not ISO-8601: the single most common feed-validator failure.
        $xml .= '  <lastBuildDate>'.$built->toRfc2822String()."</lastBuildDate>\n";
        $xml .= '  <atom:link href="'.self::xmlText(route('blog.feed')).'" rel="self" type="application/rss+xml" />'."\n";

        foreach ($articles as $article) {
            $xml .= "  <item>\n";
            $xml .= '    <title>'.self::xmlText($article->title)."</title>\n";
            $xml .= '    <link>'.self::xmlText(route('blog.show', $article->slug))."</link>\n";
            // Not a permalink: editing a slug would otherwise resurface an old
            // article in every subscriber's reader as though it were new.
            $xml .= '    <guid isPermaLink="false">article-'.$article->id."</guid>\n";
            $xml .= '    <pubDate>'.$article->published_at->toRfc2822String()."</pubDate>\n";

            if ($article->category) {
                $xml .= '    <category>'.self::xmlText($article->category->name)."</category>\n";
            }

            $xml .= '    <description>'.self::cdata((string) $article->excerpt)."</description>\n";
            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n";
        $xml .= '</rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /**
     * Text safe to drop into an XML node.
     *
     * XML 1.0 forbids most control characters outright, and Word and Google
     * Docs pastes carry them; invalid UTF-8 from the same source would break
     * the whole document rather than one item.
     */
    private static function xmlText(string $value): string
    {
        return e(self::xmlSafe($value));
    }

    /**
     * A CDATA section. Control characters are illegal even inside one, and a
     * literal ]]> in the text would close the section early and spill the rest
     * of the article into the markup.
     */
    private static function cdata(string $value): string
    {
        $value = str_replace(']]>', ']]]]><![CDATA[>', self::xmlSafe($value));

        return '<![CDATA['.$value.']]>';
    }

    private static function xmlSafe(string $value): string
    {
        // Drop invalid byte sequences first, then the control characters XML
        // 1.0 does not allow (tab, newline and carriage return are fine).
        $value = (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }

    /** Published posts, newest first, without the body columns. */
    private function listing()
    {
        return Article::published()
            ->select(self::LIST_COLUMNS)
            ->with('category:id,name,slug')
            ->latest('published_at');
    }

    /** Categories that actually have something to show. */
    private function categoriesWithPosts()
    {
        return ArticleCategory::query()
            ->whereHas('articles', fn ($query) => $query->published())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /** The master switch takes the whole blog off the site, not just the menu. */
    private function assertBlogIsOn(): void
    {
        abort_unless(BlogSettings::enabled(), 404);
    }
}
