<?php

namespace App\Services\Matching;

use App\Models\AiSetting;
use App\Models\Job;
use App\Services\Ai\AiScoreResult;

/**
 * Minimum-experience handling for match scoring — deterministic and in ONE
 * place, used by every lane: after the rubric has computed the score, hard-cap
 * it when the candidate is below the job's minimum (admin switch + cap value in
 * AI Settings) and note it in the concerns list so the UI explains why.
 *
 * The comparison uses the TOTAL years computed from the CV dates in code
 * (overlaps merged) — unchanged from before the rubric. That is a different
 * number from the job-RELEVANT years the rubric scores, by design: this gate
 * asks "has this person worked long enough at all", the rubric asks "how much of
 * it counts for this job". The total is kept out of every user-facing sentence
 * and lives only in the stored breakdown.
 */
class ExperienceGate
{
    /** @return array{years: ?float, min: ?int, below: bool} */
    public static function assess(?float $candidateYears, Job $job): array
    {
        $min = $job->min_experience_years ? (int) $job->min_experience_years : null;
        $below = $min !== null && $candidateYears !== null && $candidateYears < $min;

        return ['years' => $candidateYears, 'min' => $min, 'below' => $below];
    }

    /**
     * Enforce the cap (if enabled) on a scored result. Returns the (possibly
     * adjusted) result plus whether the cap was applied.
     *
     * @return array{result: AiScoreResult, capped: bool}
     */
    public static function apply(AiScoreResult $result, ?float $candidateYears, Job $job, ?AiSetting $settings = null): array
    {
        $settings ??= AiSetting::current();
        $a = self::assess($candidateYears, $job);

        if (! $a['below'] || ! (bool) ($settings->enforce_min_experience ?? true)) {
            return ['result' => $result, 'capped' => false];
        }

        $cap = max(0, min(100, (int) ($settings->min_experience_cap_score ?? 59)));
        if ($result->score <= $cap) {
            return ['result' => $result, 'capped' => false]; // already at/under the cap
        }

        $note = sprintf('Below the job\'s minimum experience of %d years — score capped at %d', $a['min'], $cap);

        return ['result' => MatchRubric::cap($result, $cap, 'min_experience', $note, $a), 'capped' => true];
    }
}
