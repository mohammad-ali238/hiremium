<?php

namespace App\Services\Matching;

use App\Models\AiSetting;
use App\Services\Ai\AiScoreResult;

/**
 * Career-gap handling for match scoring — the third admin gate, alongside
 * minimum experience and must-have skills.
 *
 * The verdict comes from ExperienceCalculator::careerGap(), which reads the
 * parsed CV dates (never the model): a break longer than six months between
 * roles, or no current role with the last one more than six months back. When
 * enforcement is on, a gap hard-caps the score at the admin's value (default
 * 80) and the reason is noted so the recruiter sees why.
 */
class CareerGapGate
{
    public const DEFAULT_CAP = 80;

    /**
     * @param  array|null  $gap  ExperienceCalculator::careerGap() output, or null when the CV had no datable roles
     * @return array{result: AiScoreResult, capped: bool}
     */
    public static function apply(AiScoreResult $result, ?array $gap, ?AiSetting $settings = null): array
    {
        $settings ??= AiSetting::current();

        if (! $gap || empty($gap['has_gap']) || ! (bool) ($settings->enforce_career_gap ?? true)) {
            return ['result' => $result, 'capped' => false];
        }

        $cap = max(0, min(100, (int) ($settings->career_gap_cap_score ?? self::DEFAULT_CAP)));
        if ($result->score <= $cap) {
            return ['result' => $result, 'capped' => false]; // already at/under the cap
        }

        $reasons = array_values(array_filter((array) ($gap['reasons'] ?? [])));
        $note = 'Career gap in the work history'
            .($reasons ? ' ('.implode('; ', $reasons).')' : '')
            .' — score capped at '.$cap;

        return ['result' => MatchRubric::cap($result, $cap, 'career_gap', $note, $gap), 'capped' => true];
    }
}
