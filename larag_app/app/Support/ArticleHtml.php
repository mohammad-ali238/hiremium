<?php

namespace App\Support;

use App\Models\Article;

/**
 * Renders an article body and cleans the per-article CSS box.
 *
 * Deliberately NOT App\Support\RichText: that allow-list exists to keep links
 * and images OUT of job descriptions, which is the opposite of what an article
 * needs. Article bodies are written by a trusted admin, so the default here is
 * to render exactly what was written; the Blog Settings "Sanitize pasted HTML"
 * switch is what tightens that when authoring is opened up.
 */
class ArticleHtml
{
    /** Tags whose entire contents are dropped when deriving plain text. */
    private const STRIPPED_BLOCKS = ['script', 'style', 'noscript', 'iframe'];

    /** The body to render, chosen by the article's mode. */
    public static function body(Article $article): string
    {
        $html = $article->content_mode === Article::MODE_HTML
            ? (string) $article->content_html
            : (string) $article->content_editor;

        if ($html === '') {
            return '';
        }

        // Pasted HTML only: rich-editor output is already a fragment, and a
        // full document arriving in editor mode would be the editor's bug.
        if ($article->content_mode === Article::MODE_HTML) {
            $html = self::unwrapDocument($html);
        }

        return BlogSettings::sanitizeHtml() ? self::sanitizeHtml($html) : $html;
    }

    /**
     * People paste whole documents out of Word and Google Docs, wrapper and
     * all. Keep the body's contents and drop everything around it — a stray
     * <html>/<head> inside <main> is invalid and confuses every parser after.
     */
    public static function unwrapDocument(string $html): string
    {
        if (! preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $matches)) {
            return $html;
        }

        return trim($matches[1]);
    }

    /**
     * Strip the three things that turn pasted markup into script execution:
     * <script> blocks, inline on* handlers and javascript: URLs. Only applied
     * when the setting is on — see BlogSettings::sanitizeHtml().
     */
    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*\/?>/i', '', $html) ?? $html;

        // on* handlers, quoted or bare.
        $html = preg_replace('/\son[a-z-]+\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace("/\son[a-z-]+\s*=\s*'[^']*'/i", '', $html) ?? $html;
        $html = preg_replace('/\son[a-z-]+\s*=\s*[^\s>]+/i', '', $html) ?? $html;

        // javascript: in any URL attribute, tolerating whitespace/entities.
        $html = preg_replace('/(href|src|action|formaction)\s*=\s*(["\']?)\s*javascript\s*:[^"\'>]*\2/i', '$1=$2#$2', $html) ?? $html;

        return $html;
    }

    /**
     * Clean the per-article CSS before it is printed inside <head>.
     *
     * '<' is removed outright: no real stylesheet needs it, and leaving it in
     * lets a stray '</style>' close the block early and turn the rest of the
     * box into markup. @import is dropped too — it would make every visitor's
     * browser call a third-party host, which is a privacy problem as much as a
     * security one. The result is capped so a paste accident cannot bloat the
     * page.
     */
    public static function sanitizeCss(?string $css): string
    {
        $css = trim((string) $css);

        if ($css === '' || ! BlogSettings::allowCustomCss()) {
            return '';
        }

        $css = str_replace('<', '', $css);
        $css = preg_replace('/@import\b[^;]*;?/i', '', $css) ?? $css;

        $max = BlogSettings::cssMaxBytes();

        if (strlen($css) > $max) {
            $css = substr($css, 0, $max);
        }

        return trim($css);
    }

    /**
     * Visible text, for excerpts and reading time. Script/style blocks are
     * removed with their contents first — otherwise an auto-excerpt off a Word
     * paste is a wall of mso- declarations.
     */
    public static function plainText(?string $html): string
    {
        $html = (string) $html;

        if ($html === '') {
            return '';
        }

        foreach (self::STRIPPED_BLOCKS as $tag) {
            $html = preg_replace('/<'.$tag.'\b[^>]*>.*?<\/'.$tag.'>/is', ' ', $html) ?? $html;
        }

        // Anything that renders as a break becomes a space, or the words either
        // side of it fuse — table cells especially, which otherwise turn an
        // auto-excerpt into "WeakSpecific Strong communicatorWrites...".
        $html = preg_replace(
            '/<(br|\/p|\/div|\/li|\/h[1-6]|\/tr|\/td|\/th|\/blockquote|\/figcaption|\/pre)\b[^>]*>/i',
            ' ',
            $html
        ) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Reading time in whole minutes, never zero for a non-empty article. */
    public static function readingMinutes(?string $html): int
    {
        $words = str_word_count(self::plainText($html));

        return max(1, (int) ceil($words / 200));
    }
}
