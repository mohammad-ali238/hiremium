<?php

namespace App\Services\Tools\Stages;

use App\Models\ToolRun;
use App\Services\Tools\Contracts\StageResult;

/**
 * Finishes a Job Description Analyzer run: denormalizes the score and band onto
 * the run for the headline and the admin filters.
 *
 * There is no working data to strip — this tool holds no file and no extracted
 * text, so `jd_result` IS the report.
 */
class BuildJdReportStage extends Stage
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
        $jd = (array) (((array) $run->output)['jd_result'] ?? []);
        $score = isset($jd['score']) ? (int) $jd['score'] : null;

        $run->score = $score;
        $run->band = $jd['band'] ?? ToolRun::bandFor($score);
        $run->save();

        return StageResult::local(['score' => $score, 'band' => $run->band]);
    }
}
