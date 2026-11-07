<?php

namespace App\Services\Tools\Support;

use App\Models\Job;
use App\Services\Tools\Contracts\ToolDefinition;

/**
 * Turns raw form input into the three things every run needs: the tidy values
 * stored on the run (and shown back as "the job you submitted"), the hash that
 * recognises the same submission tomorrow-or-under-another-email, and the plain
 * text the AI reads.
 *
 * Driven by the tool's own `fields()` schema, so a future tool with different
 * fields needs no change here.
 */
class JobPayload
{
    /**
     * Clean the submitted values: trim text, drop empties, cast numbers, and
     * de-duplicate tag lists while keeping the order the visitor typed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(ToolDefinition $tool, array $data): array
    {
        $clean = [];

        foreach ($tool->fields() as $key => $field) {
            $value = $data[$key] ?? null;

            $clean[$key] = match ($field['type'] ?? 'text') {
                'number' => is_numeric($value) ? (int) $value : null,
                'tags' => self::tags($value),
                default => self::text($value),
            };
        }

        return $clean;
    }

    /**
     * Fingerprint of a normalized payload: order-independent, case-insensitive
     * and blind to whitespace, so re-typing the same job with a stray newline
     * is still recognised as the same job.
     *
     * @param  array<string, mixed>  $normalized
     */
    public static function hash(array $normalized): string
    {
        $parts = [];

        foreach ($normalized as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_array($value)) {
                $value = array_map(fn ($v) => self::flatten((string) $v), $value);
                sort($value);
                $value = implode(',', $value);
            } else {
                $value = self::flatten((string) $value);
            }

            $parts[$key] = $value;
        }

        ksort($parts);

        return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** One hash for "this exact resume against this exact job". */
    public static function comboHash(string $inputHash, ?string $fileHash): string
    {
        return hash('sha256', $inputHash.'|'.($fileHash ?? ''));
    }

    /**
     * The job as the AI reads it — the same shape the recruiter pipeline sends,
     * capped so one free run cannot blow up the token bill.
     *
     * @param  array<string, mixed>  $normalized
     */
    public static function toText(array $normalized, ?int $maxChars = null): string
    {
        $maxChars ??= (int) config('tools.limits.job_text_chars', 12000);

        $lines = array_filter([
            filled($normalized['title'] ?? null) ? 'Job title: '.$normalized['title'] : null,
            filled($normalized['employment_type'] ?? null) ? 'Employment type: '.$normalized['employment_type'] : null,
            filled($normalized['location_type'] ?? null) ? 'Work mode: '.$normalized['location_type'] : null,
            filled($normalized['location'] ?? null) ? 'Location: '.$normalized['location'] : null,
            isset($normalized['min_experience_years']) && $normalized['min_experience_years'] !== null
                ? 'Minimum experience: '.$normalized['min_experience_years'].' years'
                : null,
            filled($normalized['education'] ?? null) ? 'Education: '.$normalized['education'] : null,
            filled($normalized['must_have_skills'] ?? null) ? 'Must-have skills: '.implode(', ', $normalized['must_have_skills']) : null,
            filled($normalized['nice_to_have_skills'] ?? null) ? 'Nice-to-have skills: '.implode(', ', $normalized['nice_to_have_skills']) : null,
            filled($normalized['description'] ?? null) ? "\nJob description:\n".$normalized['description'] : null,
        ]);

        return mb_substr(implode("\n", $lines), 0, $maxChars);
    }

    /**
     * The submitted job as a transient Job model — never saved, never given an
     * id. It exists so a free tool can reuse the recruiter machinery verbatim:
     * `toMatchingText()`, the experience gate and the skills gate all speak Job.
     *
     * @param  array<string, mixed>  $normalized
     */
    public static function toJob(array $normalized): Job
    {
        return Job::fromSnapshot($normalized);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private static function tags(mixed $value): array
    {
        $tags = [];

        foreach ((array) $value as $tag) {
            $tag = trim((string) $tag);

            if ($tag !== '' && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /** Lowercase, whitespace-collapsed form used only for hashing. */
    private static function flatten(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? '');
    }
}
