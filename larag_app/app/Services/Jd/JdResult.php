<?php

namespace App\Services\Jd;

/**
 * The normalized outcome of one job-description analysis.
 *
 * normalize() is the trust boundary over the model's raw JSON: the score is
 * clamped, every list is cleaned to plain strings, and `level_issue` collapses
 * to null when empty — which is what hides the Level Issue section on the
 * report rather than rendering an empty box.
 */
final class JdResult
{
    /**
     * @param  list<string>  $lacking
     * @param  list<string>  $improvements
     * @param  list<string>  $mustHaveSkills
     * @param  list<string>  $softSkills
     */
    public function __construct(
        public readonly int $score,
        public readonly string $band,
        public readonly array $lacking,
        public readonly ?string $levelIssue,
        public readonly array $improvements,
        public readonly array $mustHaveSkills,
        public readonly array $softSkills,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $tokensUsed,
        public readonly array $rawResponse = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function normalize(array $raw, string $provider, string $model, ?int $tokensUsed): self
    {
        $score = max(0, min(100, (int) ($raw['score'] ?? 0)));

        return new self(
            score: $score,
            band: self::band($score),
            lacking: self::stringList($raw['lacking'] ?? []),
            levelIssue: self::text($raw['level_issue'] ?? null),
            improvements: self::stringList($raw['improvements'] ?? []),
            mustHaveSkills: self::stringList($raw['must_have_skills'] ?? []),
            softSkills: self::stringList($raw['soft_skills'] ?? []),
            provider: $provider,
            model: $model,
            tokensUsed: $tokensUsed,
            rawResponse: $raw,
        );
    }

    /** The stored/rendered shape (goes into the run's output JSON). */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band,
            'lacking' => $this->lacking,
            'level_issue' => $this->levelIssue,
            'improvements' => $this->improvements,
            'must_have_skills' => $this->mustHaveSkills,
            'soft_skills' => $this->softSkills,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }

    /** Same bands as the rest of the app, without coupling to a model. */
    private static function band(int $score): string
    {
        $bands = (array) config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);

        return match (true) {
            $score >= (int) ($bands['excellent'] ?? 90) => 'excellent',
            $score >= (int) ($bands['good'] ?? 75) => 'good',
            $score >= (int) ($bands['fair'] ?? 60) => 'fair',
            default => 'weak',
        };
    }

    /** Empty / whitespace-only becomes null, which hides the Level Issue section. */
    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Clean a list to plain, unique, non-empty strings. Tolerates the model
     * returning objects like {"text": "..."} instead of bare strings.
     *
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        $out = [];

        foreach ((array) $value as $item) {
            if (is_array($item)) {
                $item = $item['text'] ?? $item['skill'] ?? $item['name'] ?? reset($item);
            }

            $item = trim((string) $item);

            if ($item !== '' && ! in_array($item, $out, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
