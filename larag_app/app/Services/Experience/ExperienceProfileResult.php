<?php

namespace App\Services\Experience;

use App\Services\Jd\SeniorityLevels;
use Illuminate\Support\Str;

/**
 * The normalized outcome of one experience extraction.
 *
 * normalize() is the trust boundary over the model's raw JSON. The seniority is
 * snapped back onto the ladder: the model is told to copy one of the labels
 * exactly, and when it improvises ("Sr.", "senior engineer") the closest real
 * rung is used rather than printing whatever it felt like saying.
 */
final class ExperienceProfileResult
{
    public function __construct(
        public readonly string $industry,
        public readonly string $subIndustry,
        public readonly string $seniority,
        public readonly string $about,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $tokensUsed,
        public readonly array $rawResponse = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function normalize(array $raw, string $provider, string $model, ?int $tokensUsed): self
    {
        return new self(
            industry: self::text($raw['industry'] ?? ''),
            subIndustry: self::text($raw['sub_industry'] ?? ''),
            seniority: self::seniority($raw['seniority'] ?? ''),
            about: Str::limit(self::text($raw['about'] ?? ''), 600),
            provider: $provider,
            model: $model,
            tokensUsed: $tokensUsed,
            rawResponse: $raw,
        );
    }

    public function isEmpty(): bool
    {
        return $this->about === '' && $this->industry === '' && $this->seniority === '';
    }

    /** The stored/rendered shape (goes into the run's output JSON). */
    public function toArray(): array
    {
        return [
            'industry' => $this->industry,
            'sub_industry' => $this->subIndustry,
            'seniority' => $this->seniority,
            'about' => $this->about,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }

    /**
     * A label from the ladder, or '' — never the model's own wording.
     *
     * Matched on the label, then the key, then by containment, so "Senior",
     * "senior", "mid_level" and "Mid-Level Engineer" all land on a real rung.
     */
    private static function seniority(mixed $value): string
    {
        $given = mb_strtolower(self::text($value));

        if ($given === '') {
            return '';
        }

        foreach (SeniorityLevels::LEVELS as $key => $label) {
            if ($given === mb_strtolower($label) || $given === mb_strtolower($key)) {
                return $label;
            }
        }

        foreach (SeniorityLevels::LEVELS as $label) {
            // "Executive (C-level)" should still match a bare "executive".
            $bare = mb_strtolower(trim(Str::before($label, '(')));

            if ($bare !== '' && str_contains($given, $bare)) {
                return $label;
            }
        }

        return '';
    }

    private static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }
}
