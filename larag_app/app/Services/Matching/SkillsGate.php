<?php

namespace App\Services\Matching;

use App\Models\AiSetting;
use App\Models\Job;
use App\Services\Ai\AiScoreResult;

/**
 * Must-have skill enforcement — deterministic and in ONE place for both lanes.
 *
 * The AI (which alone can judge semantic equivalence across synonyms,
 * abbreviations and languages) returns a verdict PER must-have skill listed on
 * the job. This class reconciles those verdicts against the job's own list in
 * code — every must-have gets exactly one entry; anything the model skipped
 * counts as unmet — then hard-caps the score if any must-have is missing
 * (admin switch + cap in AI Settings), noting the gap in the concerns.
 */
class SkillsGate
{
    /**
     * @return array{coverage: array<int, array{skill:string, met:bool, evidence:string}>, total:int, met:int, missing:string[]}
     */
    public static function reconcile(AiScoreResult $result, Job $job): array
    {
        $required = array_values(array_filter(array_map('trim', (array) ($job->must_have_skills ?? []))));
        if (! $required) {
            return ['coverage' => [], 'total' => 0, 'met' => 0, 'missing' => []];
        }

        // Index the model's verdicts by normalised skill name.
        $verdicts = [];
        foreach ($result->mustHaveCoverage as $row) {
            $verdicts[self::key($row['skill'])] = $row;
        }

        $coverage = [];
        $missing = [];
        foreach ($required as $skill) {
            $v = $verdicts[self::key($skill)] ?? null;
            $met = $v ? (bool) $v['met'] : false; // model skipped it → treat as unmet
            $coverage[] = ['skill' => $skill, 'met' => $met, 'evidence' => $v['evidence'] ?? ''];
            if (! $met) {
                $missing[] = $skill;
            }
        }

        return [
            'coverage' => $coverage,
            'total' => count($required),
            'met' => count($required) - count($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Enforce the cap (if enabled) when any must-have is unmet.
     *
     * @return array{result: AiScoreResult, capped: bool, summary: array}
     */
    public static function apply(AiScoreResult $result, Job $job, ?AiSetting $settings = null): array
    {
        $settings ??= AiSetting::current();
        $r = self::reconcile($result, $job);

        // Always store the reconciled coverage (job's list, in order) — even when not capping.
        $base = self::withCoverage($result, $r['coverage']);

        if ($r['total'] === 0 || ! $r['missing'] || ! (bool) ($settings->enforce_must_have_skills ?? true)) {
            return ['result' => $base, 'capped' => false, 'summary' => $r];
        }

        // Tiered cap: on a LONG must-have list (more than N skills) a single miss
        // is forgivable → soft cap; two or more misses, or any miss on a short
        // list, → hard cap. Both thresholds are admin-configurable.
        $hardCap = max(0, min(100, (int) ($settings->must_have_cap_score ?? 74)));
        $softCap = max(0, min(100, (int) ($settings->must_have_soft_cap_score ?? 88)));
        $softMin = max(1, (int) ($settings->must_have_soft_cap_min_skills ?? 4));
        $soft = count($r['missing']) === 1 && $r['total'] > $softMin;
        $cap = $soft ? max($softCap, $hardCap) : $hardCap;

        if ($base->score <= $cap) {
            return ['result' => $base, 'capped' => false, 'summary' => $r];
        }

        $note = sprintf('Missing must-have skill%s: %s (%d of %d met) — score capped at %d%s',
            count($r['missing']) === 1 ? '' : 's', implode(', ', $r['missing']), $r['met'], $r['total'], $cap,
            $soft ? ' (one miss on a long list)' : '');

        $adjusted = MatchRubric::cap($base, $cap, 'must_have_skills', $note, $r + ['tier' => $soft ? 'soft' : 'hard'])
            ->with(['missingSkills' => array_values(array_unique(array_merge($r['missing'], $base->missingSkills)))]);

        return ['result' => $adjusted, 'capped' => true, 'summary' => $r];
    }

    private static function withCoverage(AiScoreResult $r, array $coverage): AiScoreResult
    {
        return $r->with(['mustHaveCoverage' => $coverage]);
    }

    /** Loose key for matching the model's echoed skill name back to the job's entry. */
    private static function key(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s)) ?? mb_strtolower($s);
    }
}
