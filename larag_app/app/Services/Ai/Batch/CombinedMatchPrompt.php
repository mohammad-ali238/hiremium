<?php

namespace App\Services\Ai\Batch;

use App\Models\AiSetting;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\MatchPrompt;
use App\Services\ResumeParsing\ParseConfidence;

/**
 * Prompt for the async batch lane: a single request per CV that BOTH extracts
 * the candidate's details and scores them against the job. Used only when the
 * parse-resume and match-score providers are the same (so one call can honour
 * both); mixed-provider workspaces fall back to the two-call live lane.
 */
class CombinedMatchPrompt
{
    /** The combined parse + evidence contract — fixed in code, not admin-editable. */
    public static function system(AiSetting $settings): string
    {
        return MatchPrompt::combined();
    }

    public static function user(string $jobText, string $cvText): string
    {
        return "JOB:\n{$jobText}\n\n---\nAPPLICATION (raw CV text):\n{$cvText}";
    }

    /**
     * Split a combined JSON response into a parsed-candidate data array (the
     * canonical camelCase schema the pipeline uses) and an AiScoreResult.
     *
     * @return array{data: array, result: AiScoreResult}|null
     */
    public static function parse(string $content, AiSetting $settings): ?array
    {
        $json = json_decode($content, true);
        if (! is_array($json) || ! isset($json['score'])) {
            return null;
        }

        $candidate = is_array($json['candidate'] ?? null) ? $json['candidate'] : [];

        $data = [
            'fullName' => (string) ($candidate['fullName'] ?? ''),
            'email' => (string) ($candidate['email'] ?? ''),
            'phone' => (string) ($candidate['phone'] ?? ''),
            'jobTitle' => (string) ($candidate['jobTitle'] ?? ''),
            'summary' => (string) ($candidate['summary'] ?? ''),
            'skills' => array_values(array_filter((array) ($candidate['skills'] ?? []))),
            'experience' => (array) ($candidate['experience'] ?? []),
            'education' => (array) ($candidate['education'] ?? []),
            'confidence' => ParseConfidence::normalize($candidate['confidence'] ?? null),
        ];

        $result = new AiScoreResult(
            score: (int) max(0, min(100, $json['score'])),
            reason: (string) ($json['reason'] ?? ''),
            tokensUsed: null,
            provider: $settings->default_provider,
            model: $settings->default_model,
            rawResponse: $json,
            strengths: array_values(array_filter(array_map('strval', (array) ($json['strengths'] ?? [])))),
            concerns: array_values(array_filter(array_map('strval', (array) ($json['concerns'] ?? [])))),
            // Findings only — MatchRubric turns these into the score, the
            // matched/missing skills and the must-have coverage.
            evidence: MatchPrompt::evidence($json),
        );

        return ['data' => $data, 'result' => $result];
    }
}
