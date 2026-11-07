<?php

namespace App\Services\Skills;

use Illuminate\Support\Str;

/**
 * The normalized outcome of one skills extraction.
 *
 * normalize() is the trust boundary over the model's raw JSON: every list is
 * cleaned to unique plain strings and capped, and "about" is trimmed to a few
 * sentences — so a model that ignores the word limit cannot push a wall of text
 * onto the report.
 */
final class SkillProfileResult
{
    /** Keeps one runaway list from filling the page. */
    private const MAX_ITEMS = 20;

    /**
     * @param  list<string>  $coreSkills
     * @param  list<string>  $softSkills
     * @param  list<string>  $tools
     */
    public function __construct(
        public readonly string $industry,
        public readonly string $subIndustry,
        public readonly string $seniority,
        public readonly array $coreSkills,
        public readonly array $softSkills,
        public readonly array $tools,
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
            seniority: self::text($raw['seniority'] ?? ''),
            coreSkills: self::stringList($raw['core_skills'] ?? []),
            softSkills: self::stringList($raw['soft_skills'] ?? []),
            tools: self::stringList($raw['tools'] ?? []),
            about: Str::limit(self::text($raw['about'] ?? ''), 600),
            provider: $provider,
            model: $model,
            tokensUsed: $tokensUsed,
            rawResponse: $raw,
        );
    }

    /** True when the model gave us nothing worth rendering. */
    public function isEmpty(): bool
    {
        return $this->coreSkills === []
            && $this->softSkills === []
            && $this->tools === []
            && $this->about === '';
    }

    /** The stored/rendered shape (goes into the run's output JSON). */
    public function toArray(): array
    {
        return [
            'industry' => $this->industry,
            'sub_industry' => $this->subIndustry,
            'seniority' => $this->seniority,
            'core_skills' => $this->coreSkills,
            'soft_skills' => $this->softSkills,
            'tools' => $this->tools,
            'about' => $this->about,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }

    /** @return list<string> Unique, trimmed, non-empty, capped. */
    private static function stringList(mixed $value): array
    {
        $items = [];

        foreach ((array) $value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $text = self::text((string) $item);

            // Case-insensitive dedupe: models happily return "SQL" and "sql".
            if ($text !== '' && ! in_array(mb_strtolower($text), array_map('mb_strtolower', $items), true)) {
                $items[] = $text;
            }
        }

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    private static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }
}
