<?php

namespace App\Services\ResumeParsing;

/**
 * The parser's own certainty that it read the CV correctly and extracted the
 * right data — the number ATS integrations ask for. Stored as a 0-1 decimal
 * with 2 places, exactly as the model reports it, and shown to people as a
 * percentage.
 *
 * Below the minimum the document counts as unread: the matching-score AI call
 * is skipped rather than ranking a candidate whose details we cannot vouch for.
 * A parse carrying NO confidence at all (a row written before this existed, or
 * an admin prompt that no longer asks for it) is never blocked — only a number
 * below the floor is.
 */
class ParseConfidence
{
    /** Floor used when nothing is configured. */
    public const DEFAULT_MINIMUM = 0.75;

    /** Below this the CV is not scored at all. */
    public static function minimum(): float
    {
        return (float) config('hiremium.ai.min_parse_confidence', self::DEFAULT_MINIMUM);
    }

    /**
     * A model's answer turned into a 0-1 decimal, or null when it said nothing
     * usable. Tolerates the variants models actually return: 0.85, "0.85",
     * "85%", 85.
     */
    public static function normalize(mixed $raw): ?float
    {
        if (is_string($raw)) {
            $raw = trim(str_replace('%', '', $raw));
        }

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        // "85" means 85%, not 8500%.
        if ($value > 1) {
            $value = $value / 100;
        }

        return round(max(0.0, min(1.0, $value)), 2);
    }

    /** The same value as a whole percentage for display, or null when unknown. */
    public static function percent(mixed $confidence): ?int
    {
        $value = is_float($confidence) || $confidence === null ? $confidence : self::normalize($confidence);

        return $value === null ? null : (int) round($value * 100);
    }

    /** Ready-to-print percentage, e.g. "82%" — or a dash when unknown. */
    public static function format(mixed $confidence, string $placeholder = '—'): string
    {
        $percent = self::percent($confidence);

        return $percent === null ? $placeholder : $percent.'%';
    }

    /** How the percentage reads in words. */
    public static function label(mixed $confidence): string
    {
        $value = is_float($confidence) || $confidence === null ? $confidence : self::normalize($confidence);

        return match (true) {
            $value === null => 'Not reported',
            $value >= 0.90 => 'High',
            $value >= self::minimum() => 'Good',
            $value >= 0.50 => 'Low',
            default => 'Very low',
        };
    }

    /** May a parse with this confidence be scored against a job? */
    public static function isSufficient(mixed $confidence): bool
    {
        $value = is_float($confidence) || $confidence === null ? $confidence : self::normalize($confidence);

        return $value === null || $value >= self::minimum();
    }

    /** Pull the confidence out of a parsed-resume data array. */
    public static function fromData(array $data): ?float
    {
        return self::normalize($data['confidence'] ?? null);
    }

    /**
     * Why this CV was not scored, in words a recruiter (or a visitor on the
     * free tools) can act on. Carries both numbers: an ATS user wants to know
     * how far off the document was, not just that it failed.
     */
    public static function failureMessage(mixed $confidence): string
    {
        $read = self::format($confidence, 'too little');
        $floor = self::percent(self::minimum()).'%';

        return "We could only read this resume with {$read} certainty ({$floor} is required), so it was not scored. "
            .'The file may be scanned, low quality or incomplete — upload a clearer, text-based copy and run it again.';
    }

    /**
     * The confidence contract, appended to the admin's saved parsing prompt at
     * call time. Returns '' when that prompt already asks for the field, so an
     * admin who has written their own wording keeps it.
     */
    public static function promptAddendum(?string $prompt): string
    {
        if ($prompt !== null && str_contains($prompt, '"confidence"')) {
            return '';
        }

        return <<<'TXT'


Also return ONE extra top-level key alongside the schema above:
- "confidence": a number between 0 and 1 with at most 2 decimals (e.g. 0.9, 0.8, 0.75) — your own certainty that you read this document correctly and extracted its data accurately. Judge it from the source itself: 0.95-1 = clean machine-readable text with every section clear; 0.75-0.94 = readable with some ambiguity (odd layout, a few unclear dates or sections); below 0.75 = scanned, blurred, partly unreadable, or so sparse that key details had to be left empty. Never report high confidence for a document you could not actually read.
TXT;
    }
}
