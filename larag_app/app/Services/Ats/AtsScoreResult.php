<?php

namespace App\Services\Ats;

/**
 * The normalized outcome of one ATS scoring call — the single shape both the
 * free tool and (later) logged-in callers store and render.
 *
 * `normalize()` is the trust boundary over the model's raw JSON: every score is
 * clamped to 0-100 and every feedback item is coerced to {section, text}, so a
 * loose or hostile response can never reach the view malformed.
 */
final class AtsScoreResult
{
    /**
     * @param  array{ats:int,summary:int,experience:int,skills:int}  $subScores
     * @param  list<string>  $targetRoles
     * @param  list<array{section:string,text:string}>  $strengths
     * @param  list<array{section:string,text:string}>  $weaknesses
     * @param  list<array{section:string,text:string}>  $criticalIssues
     * @param  list<array{section:string,text:string}>  $suggestions
     */
    public function __construct(
        public readonly int $score,
        public readonly string $band,
        public readonly array $subScores,
        public readonly ?string $industry,
        public readonly ?string $specialization,
        public readonly ?string $seniority,
        public readonly array $targetRoles,
        public readonly array $strengths,
        public readonly array $weaknesses,
        public readonly array $criticalIssues,
        public readonly array $suggestions,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $tokensUsed,
        public readonly array $rawResponse = [],
    ) {}

    /**
     * Build from a provider's decoded JSON.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function normalize(array $raw, string $provider, string $model, ?int $tokensUsed): self
    {
        $scores = (array) ($raw['scores'] ?? []);
        $overall = self::clamp($scores['overall'] ?? 0);

        return new self(
            score: $overall,
            band: self::band($overall),
            subScores: [
                'ats' => self::clamp($scores['ats'] ?? 0),
                'summary' => self::clamp($scores['summary'] ?? 0),
                'experience' => self::clamp($scores['experience'] ?? 0),
                'skills' => self::clamp($scores['skills'] ?? 0),
            ],
            industry: self::str($raw['industry'] ?? null),
            specialization: self::str($raw['specialization'] ?? null),
            seniority: self::str($raw['seniority'] ?? null),
            targetRoles: self::roles($raw['target_roles'] ?? []),
            strengths: self::taggedList($raw['strengths'] ?? []),
            weaknesses: self::taggedList($raw['weaknesses'] ?? []),
            criticalIssues: self::taggedList($raw['critical_issues'] ?? []),
            suggestions: self::taggedList($raw['suggestions'] ?? []),
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
            'sub_scores' => $this->subScores,
            'industry' => $this->industry,
            'specialization' => $this->specialization,
            'seniority' => $this->seniority,
            'target_roles' => $this->targetRoles,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'critical_issues' => $this->criticalIssues,
            'suggestions' => $this->suggestions,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }

    private static function clamp(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /** Same bands as ToolRun::bandFor / the score pill, without coupling to a model. */
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

    private static function str(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private static function roles(mixed $value): array
    {
        $roles = [];

        foreach ((array) $value as $role) {
            $role = trim((string) $role);

            if ($role !== '' && ! in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * Coerce a feedback list to {section, text}. Accepts the object form the
     * prompt asks for, and tolerates a bare string (→ section "general").
     *
     * @return list<array{section:string,text:string}>
     */
    private static function taggedList(mixed $value): array
    {
        $allowed = ['summary', 'skills', 'experience', 'general'];
        $out = [];

        foreach ((array) $value as $item) {
            if (is_string($item)) {
                $text = trim($item);
                $section = 'general';
            } else {
                $item = (array) $item;
                $text = trim((string) ($item['text'] ?? ''));
                $section = strtolower(trim((string) ($item['section'] ?? 'general')));
            }

            if ($text === '') {
                continue;
            }

            $out[] = [
                'section' => in_array($section, $allowed, true) ? $section : 'general',
                'text' => $text,
            ];
        }

        return $out;
    }
}
