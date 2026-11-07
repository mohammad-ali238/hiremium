<?php

namespace App\Support;

/**
 * Sanitizes rich-text HTML (job descriptions) down to a small, useful allow-list
 * and measures its plain-text length. Single source of truth for BOTH the /app
 * Trix editor and the admin RichEditor, so both store the same safe subset.
 */
class RichText
{
    /** Allowed tags with their permitted attributes. */
    public const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'h2' => [], 'h3' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [],
        'div' => [], // Trix wraps blocks in <div>; normalised to <p> below
        // NOTE: no <a> — job descriptions may not contain links.
    ];

    /** Plain-text length bounds for a job description. */
    public const MIN_CHARS = 50;

    public const MAX_CHARS = 8000;

    /** Strip everything not on the allow-list; normalise Trix <div> blocks to <p>; harden links. */
    public static function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        // Kill scripts/styles wholesale (content included), then unknown tags (content kept).
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|textarea|button|svg|math)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, array_map(fn ($t) => "<{$t}>", array_keys(self::ALLOWED)));

        // Strip disallowed attributes (incl. on*, style, class) from allowed tags.
        $html = preg_replace_callback('#<([a-z0-9]+)(\s[^>]*)?>#i', function ($m) {
            $tag = strtolower($m[1]);
            $allowedAttrs = self::ALLOWED[$tag] ?? [];
            if (empty($allowedAttrs) || empty($m[2])) {
                return "<{$tag}>";
            }
            $kept = [];
            preg_match_all('#([a-z-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i', $m[2], $attrs, PREG_SET_ORDER);
            foreach ($attrs as $a) {
                $name = strtolower($a[1]);
                $value = $a[3] ?? $a[4] ?? $a[5] ?? '';
                if (! in_array($name, $allowedAttrs, true)) {
                    continue;
                }
                $kept[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"';
            }

            return '<'.$tag.($kept ? ' '.implode(' ', $kept) : '').'>';
        }, $html) ?? $html;

        // Trix emits <div>…</div> per block; make them paragraphs.
        $html = preg_replace('#<div>#i', '<p>', $html) ?? $html;
        $html = preg_replace('#</div>#i', '</p>', $html) ?? $html;

        // Quill encodes ordinary spaces as &nbsp; in its HTML output — normalise.
        $html = str_replace('&nbsp;', ' ', $html);

        // Drop empty paragraphs and collapse whitespace-only content.
        $html = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#i', '', $html) ?? $html;
        $html = trim($html);

        return $html === '' ? null : $html;
    }

    /** Visible-text length (what users perceive as "how much they wrote"). */
    public static function length(?string $html): int
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strlen(trim($text));
    }

    /** Plain text for AI prompts (keeps line structure roughly). */
    public static function toPlainText(?string $html): string
    {
        $text = (string) $html;
        $text = preg_replace('#</(p|div|li|h2|h3|blockquote)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li>#i', '- ', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
