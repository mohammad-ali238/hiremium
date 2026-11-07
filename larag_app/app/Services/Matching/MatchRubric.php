<?php

namespace App\Services\Matching;

use App\Models\Job;
use App\Services\Ai\AiScoreResult;

/**
 * The match score, computed in code from the model's findings.
 *
 * The AI reports evidence only (see App\Services\Ai\MatchPrompt); this class
 * owns the arithmetic, so the same evidence always produces the same number and
 * every point is traceable to a quota and a percentage. The model's own score is
 * kept on the result as $aiScore (admin-only) and never enters the total.
 *
 * Quotas — 100 points, split on whether the JOB lists nice-to-have skills:
 *
 *                        with nice-to-haves   without
 *   Skills                       50              50
 *   Experience                   25              25
 *   Education                    10              15
 *   Nice-to-have skills          10               —
 *   Communication & motivation    5              10
 */
class MatchRubric
{
    public const WEIGHTS_WITH_NICE = [
        'skills' => 50, 'experience' => 25, 'education' => 10, 'nice_to_have' => 10, 'communication' => 5,
    ];

    public const WEIGHTS_WITHOUT_NICE = [
        'skills' => 50, 'experience' => 25, 'education' => 15, 'communication' => 10,
    ];

    public static function labels(): array
    {
        return [
            'skills' => 'Skills',
            'experience' => 'Experience',
            'education' => 'Education',
            'nice_to_have' => 'Nice-to-have skills',
            'communication' => 'Communication & motivation',
        ];
    }

    /** @return array<string, int> */
    public static function weights(bool $hasNiceToHave): array
    {
        return $hasNiceToHave ? self::WEIGHTS_WITH_NICE : self::WEIGHTS_WITHOUT_NICE;
    }

    /**
     * How much of the experience quota a candidate's JOB-RELEVANT years earn.
     * Nothing relevant earns nothing; the lowest band is for a real but very
     * short relevant stint.
     */
    public static function experiencePercent(float $relevantYears): int
    {
        return match (true) {
            $relevantYears <= 0 => 0,
            $relevantYears < 0.5 => 30,
            $relevantYears < 3 => 50,
            $relevantYears <= 5 => 85,
            default => 100,
        };
    }

    /** Percentage of a coverage list that is met. */
    public static function coveragePercent(array $coverage): int
    {
        $total = count($coverage);
        if ($total === 0) {
            return 0;
        }

        $met = count(array_filter($coverage, fn ($row) => ! empty($row['met'])));

        return (int) round($met / $total * 100);
    }

    /**
     * Score the model's findings.
     *
     * $job is the frozen job snapshot when there is one (recruiter pipeline and
     * the public tools); the API lane has no structured job, so it passes null
     * and the nice-to-have dimension simply never applies.
     *
     * @param  array|null  $careerGap  ExperienceCalculator::careerGap() output, when the CV was parsed
     */
    public static function apply(AiScoreResult $result, ?Job $job = null, ?array $careerGap = null): AiScoreResult
    {
        $evidence = $result->evidence;
        if (! $evidence) {
            return $result; // nothing to compute from (legacy/fake result) — leave it alone
        }

        $jobNiceToHave = array_values(array_filter(array_map('trim', (array) ($job?->nice_to_have_skills ?? []))));
        $jobMustHave = array_values(array_filter(array_map('trim', (array) ($job?->must_have_skills ?? []))));

        // A nice-to-have dimension exists only when the JOB saved one. The model
        // echoes the job's list back, but the job snapshot decides.
        $hasNiceToHave = $jobNiceToHave !== [];
        $weights = self::weights($hasNiceToHave);

        // The model's skill verdicts only stand in for must-haves when the job
        // actually listed them; skills it inferred itself are NOT must-haves and
        // must never drive the must-have coverage UI or its cap.
        $assumed = (bool) $evidence['skills_assumed'] || $jobMustHave === [];
        $skills = $evidence['skills'];

        $percents = [
            'skills' => self::coveragePercent($skills),
            'experience' => self::experiencePercent((float) $evidence['relevant_years']),
            'education' => $evidence['education_match'] ? 100 : 0,
            'communication' => (int) $evidence['communication'],
        ];
        if ($hasNiceToHave) {
            $percents['nice_to_have'] = self::coveragePercent($evidence['nice_to_have']);
        }

        $dimensions = [];
        $total = 0.0;
        foreach ($weights as $key => $quota) {
            $percent = (int) ($percents[$key] ?? 0);
            $points = round($quota * $percent / 100, 2);
            $total += $points;
            $dimensions[$key] = [
                'label' => self::labels()[$key] ?? $key,
                'quota' => $quota,
                'percent' => $percent,
                'points' => $points,
            ];
        }

        $score = (int) max(0, min(100, round($total)));

        $breakdown = [
            'version' => 1,
            'score' => $score,
            'dimensions' => $dimensions,
            'skills_assumed' => $assumed,
            'relevant_experience_years' => (float) $evidence['relevant_years'],
            'education_evidence' => $evidence['education_evidence'],
            'nice_to_have_coverage' => $hasNiceToHave ? $evidence['nice_to_have'] : [],
            'career_gap' => $careerGap ? [
                'has_gap' => (bool) $careerGap['has_gap'],
                'reasons' => array_values((array) ($careerGap['reasons'] ?? [])),
                'longest_gap_months' => (int) ($careerGap['longest_gap_months'] ?? 0),
                'months_since_last' => $careerGap['months_since_last'] ?? null,
            ] : null,
            'caps' => [],
        ];

        return $result->with([
            'score' => $score,
            'aiScore' => $result->aiScore ?? $result->score,
            'breakdown' => $breakdown,
            // Matched / missing skills now come from the rubric's skill check.
            'matchedSkills' => array_values(array_map(fn ($r) => $r['skill'], array_filter($skills, fn ($r) => ! empty($r['met'])))),
            'missingSkills' => array_values(array_map(fn ($r) => $r['skill'], array_filter($skills, fn ($r) => empty($r['met'])))),
            // Per-dimension 0-100 for the breakdown bars.
            'subScores' => array_map(fn ($d) => (int) $d['percent'], $dimensions),
            'mustHaveCoverage' => $assumed ? [] : $skills,
        ]);
    }

    /**
     * Lower a scored result to a cap and record why. Used by the three gates so
     * the capping note, the breakdown trail and the concerns stay consistent.
     */
    public static function cap(AiScoreResult $result, int $cap, string $key, string $note, array $context = []): AiScoreResult
    {
        $cap = max(0, min(100, $cap));

        $breakdown = $result->breakdown;
        $breakdown['caps'][] = array_merge(['key' => $key, 'cap' => $cap, 'score_before' => $result->score, 'note' => $note], $context);

        return $result->with([
            'score' => $cap,
            'reason' => rtrim($result->reason).' [Score capped: '.$note.'.]',
            'concerns' => array_values(array_unique(array_merge([$note], $result->concerns))),
            'breakdown' => $breakdown,
            'rawResponse' => array_merge($result->rawResponse, ['_cap_'.$key => ['cap' => $cap, 'score_before' => $result->score] + $context]),
        ]);
    }
}
