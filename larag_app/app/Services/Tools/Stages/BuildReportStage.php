<?php

namespace App\Services\Tools\Stages;

use App\Models\ToolRun;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\Tools\Contracts\StageResult;

/**
 * Stage 4 — assemble the report the result page renders. No AI, no network:
 * everything is already on the run, this shapes it into one predictable
 * structure and denormalises the score for admin filters.
 *
 * It also drops the working data: the raw CV text was only needed to reach the
 * parse call, and a result link is shareable — the extracted resume text has no
 * business travelling with it.
 */
class BuildReportStage extends Stage
{
    public function key(): string
    {
        return 'report';
    }

    public function label(): string
    {
        return 'Building your report';
    }

    public function iconPath(): string
    {
        return 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z';
    }

    public function run(ToolRun $run): StageResult
    {
        $output = (array) $run->output;
        $score = (array) ($output['score_result'] ?? []);
        $value = isset($score['score']) ? (int) $score['score'] : null;

        $report = [
            'score' => $value,
            'band' => ToolRun::bandFor($value),
            'reason' => (string) ($score['reason'] ?? ''),
            'strengths' => (array) ($score['strengths'] ?? []),
            'concerns' => (array) ($score['concerns'] ?? []),
            'matched_skills' => (array) ($score['matched_skills'] ?? []),
            'missing_skills' => (array) ($score['missing_skills'] ?? []),
            'sub_scores' => (array) ($score['sub_scores'] ?? []),
            'score_breakdown' => (array) ($score['score_breakdown'] ?? []),
            'must_have_coverage' => (array) ($score['must_have_coverage'] ?? []),
            'experience_years' => $output['experience_years'] ?? null,
            // How certain the parse behind this score was (shown as a percentage).
            'parse_confidence' => ParseConfidence::fromData((array) ($output['candidate'] ?? [])),
            'candidate' => (array) ($output['candidate'] ?? []),
            'job' => (array) $run->input_data,
        ];

        // Working data, dropped now that it has served its purpose.
        foreach (['resume_text', 'needs_vision', 'file_mime', 'file_size', 'score_result'] as $key) {
            unset($output[$key]);
        }

        $run->output = array_merge($output, $report);
        $run->score = $value;
        $run->band = $report['band'];
        $run->save();

        return StageResult::local(['score' => $value, 'band' => $report['band']]);
    }
}
