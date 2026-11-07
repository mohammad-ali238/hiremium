<?php

namespace App\Services\Ai;

final class AiScoreResult
{
    public function __construct(
        public readonly int $score,
        public readonly string $reason,
        public readonly ?int $tokensUsed,
        public readonly string $provider,
        public readonly string $model,
        public readonly array $rawResponse,
        // Optional detail (populated when scored with $withSkills = true).
        public readonly array $matchedSkills = [],
        public readonly array $missingSkills = [],
        public readonly array $subScores = [],
        public readonly array $strengths = [],
        public readonly array $concerns = [],
        /** @var array<int, array{skill:string, met:bool, evidence:string}> */
        public readonly array $mustHaveCoverage = [],
        // Rubric scoring (App\Services\Matching\MatchRubric): the model's raw
        // findings, its own score once the rubric total has replaced $score, and
        // the per-dimension arithmetic behind that total.
        public readonly array $evidence = [],
        public readonly ?int $aiScore = null,
        public readonly array $breakdown = [],
    ) {}

    /**
     * A copy with some fields replaced. The gates and the rubric each adjust a
     * few fields and must carry the rest through untouched — spelling out the
     * full constructor at every call site is how a field silently gets dropped.
     */
    public function with(array $overrides): self
    {
        $current = [
            'score' => $this->score,
            'reason' => $this->reason,
            'tokensUsed' => $this->tokensUsed,
            'provider' => $this->provider,
            'model' => $this->model,
            'rawResponse' => $this->rawResponse,
            'matchedSkills' => $this->matchedSkills,
            'missingSkills' => $this->missingSkills,
            'subScores' => $this->subScores,
            'strengths' => $this->strengths,
            'concerns' => $this->concerns,
            'mustHaveCoverage' => $this->mustHaveCoverage,
            'evidence' => $this->evidence,
            'aiScore' => $this->aiScore,
            'breakdown' => $this->breakdown,
        ];

        return new self(...array_merge($current, $overrides));
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'reason' => $this->reason,
            'tokens_used' => $this->tokensUsed,
            'provider' => $this->provider,
            'model' => $this->model,
            'matched_skills' => $this->matchedSkills,
            'missing_skills' => $this->missingSkills,
            'sub_scores' => $this->subScores,
            'ai_score' => $this->aiScore,
            'score_breakdown' => $this->breakdown,
        ];
    }

    /**
     * Normalise the model's per-must-have verdict list into a clean shape.
     *
     * @return array<int, array{skill:string, met:bool, evidence:string}>
     */
    public static function normalizeCoverage(mixed $raw): array
    {
        $out = [];
        foreach ((array) $raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $skill = trim((string) ($row['skill'] ?? ''));
            if ($skill === '') {
                continue;
            }
            $met = $row['met'] ?? false;
            $out[] = [
                'skill' => mb_substr($skill, 0, 100),
                'met' => is_bool($met) ? $met : in_array(strtolower((string) $met), ['1', 'true', 'yes'], true),
                'evidence' => mb_substr(trim((string) ($row['evidence'] ?? '')), 0, 200),
            ];
        }

        return $out;
    }
}
