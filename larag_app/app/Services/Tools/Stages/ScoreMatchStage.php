<?php

namespace App\Services\Tools\Stages;

use App\Models\Job;
use App\Models\ToolRun;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\AiServiceManager;
use App\Services\Ai\FakePipelineAi;
use App\Services\Matching\CareerGapGate;
use App\Services\Matching\ExperienceCalculator;
use App\Services\Matching\ExperienceGate;
use App\Services\Matching\MatchRubric;
use App\Services\Matching\SkillsGate;
use App\Services\ResumeParsing\Exceptions\LowParseConfidenceException;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\Tools\Contracts\StageResult;
use App\Services\Tools\Support\JobPayload;
use App\Services\Tools\ToolAi;
use App\Support\ToolSettings;

/**
 * Stage 3 — the match: score the parsed resume against the submitted job.
 *
 * Identical treatment to a recruiter's candidate: the same detailed scoring
 * call, the same code-computed experience total, and the same two admin gates
 * (minimum experience, unmet must-have skills) — so a free result and a paid
 * one can never disagree about the same pair.
 */
class ScoreMatchStage extends Stage
{
    public function key(): string
    {
        return 'match';
    }

    public function label(): string
    {
        return 'Matching against the job';
    }

    public function iconPath(): string
    {
        return 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z';
    }

    public function weight(): int
    {
        return 4;
    }

    public function run(ToolRun $run): StageResult
    {
        $job = JobPayload::toJob((array) $run->input_data);
        $candidate = (array) $this->read($run, 'candidate', []);

        // Same gate as the recruiter pipeline: a resume the parser could not
        // read with enough certainty is never scored, so a free result can
        // never put a number on data we don't trust.
        $confidence = ParseConfidence::fromData($candidate);
        if (! ParseConfidence::isSufficient($confidence)) {
            throw new LowParseConfidenceException(ParseConfidence::failureMessage($confidence));
        }

        $resolved = ToolAi::resolve($run->tool, 'match');
        $settings = ToolAi::settingsFor($run->tool, 'match');

        // Total experience is computed from the CV dates in code, never asked
        // of the model, then fed to it as a fact in the dynamic user message.
        $years = ExperienceCalculator::totalYears((array) ($candidate['experience'] ?? []));

        $applicationText = $this->applicationText($candidate);

        $result = FakePipelineAi::enabled()
            ? FakePipelineAi::score($candidate, (array) ($job->must_have_skills ?? []), (array) ($job->nice_to_have_skills ?? []))
            : app(AiServiceManager::class)
                ->provider($settings->default_provider)
                ->score($job->toMatchingText(), $applicationText, $settings);

        // Same arithmetic as a recruiter's candidate: the rubric scores the
        // model's findings, then the three admin caps lower it if they apply.
        $gap = ExperienceCalculator::careerGap((array) ($candidate['experience'] ?? []));
        $result = MatchRubric::apply($result, $job, $gap);

        ['result' => $result] = ExperienceGate::apply($result, $years, $job, $settings);
        ['result' => $result] = SkillsGate::apply($result, $job, $settings);
        ['result' => $result] = CareerGapGate::apply($result, $gap, $settings);

        $this->write($run, [
            'experience_years' => $years,
            'parse_confidence' => $confidence,
            'score_result' => $this->serialize($result),
        ]);

        return new StageResult(
            provider: $result->provider,
            model: $result->model,
            providerInherited: $resolved['inherited'],
            tokensTotal: $result->tokensUsed,
            payload: ToolSettings::storeRawPayloads()
                ? ['request' => ['job' => mb_substr($job->toMatchingText(), 0, 2000), 'application' => mb_substr($applicationText, 0, 2000)], 'response' => $result->rawResponse]
                : [],
            isAiCall: true,
        );
    }

    /** @return array<string, mixed> */
    private function serialize(AiScoreResult $result): array
    {
        return [
            'score' => $result->score,
            'reason' => $result->reason,
            'matched_skills' => $result->matchedSkills,
            'missing_skills' => $result->missingSkills,
            'sub_scores' => $result->subScores,
            'score_breakdown' => $result->breakdown,
            'strengths' => $result->strengths,
            'concerns' => $result->concerns,
            'must_have_coverage' => $result->mustHaveCoverage,
            'provider' => $result->provider,
            'model' => $result->model,
        ];
    }

    /** The candidate as the scoring model reads them (same shape the pipeline sends). */
    private function applicationText(array $d): string
    {
        $p = [];

        if (! empty($d['fullName'])) {
            $p[] = 'Name: '.$d['fullName'];
        }
        if (! empty($d['jobTitle'])) {
            $p[] = 'Current title: '.$d['jobTitle'];
        }
        if (! empty($d['summary'])) {
            $p[] = 'Summary: '.$d['summary'];
        }
        if (! empty($d['skills'])) {
            $p[] = 'Skills: '.implode(', ', (array) $d['skills']);
        }

        foreach ((array) ($d['experience'] ?? []) as $e) {
            $p[] = 'Experience: '.trim(($e['title'] ?? '').' at '.($e['company'] ?? '').' ('.($e['startDate'] ?? '').' - '.($e['endDate'] ?? '').')');
            if (! empty($e['description'])) {
                $p[] = $e['description'];
            }
        }

        foreach ((array) ($d['education'] ?? []) as $ed) {
            $p[] = 'Education: '.trim(($ed['degree'] ?? '').' '.($ed['field'] ?? '').' at '.($ed['institution'] ?? ''));
        }

        return implode("\n", $p) ?: 'No parseable resume content.';
    }
}
