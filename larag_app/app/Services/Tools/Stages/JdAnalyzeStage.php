<?php

namespace App\Services\Tools\Stages;

use App\Models\AiSetting;
use App\Models\ToolRun;
use App\Services\Jd\JdAnalyzer;
use App\Services\Tools\Contracts\StageResult;
use App\Support\JdSettings;
use App\Support\ToolSettings;

/**
 * The one AI call of the Job Description Analyzer.
 *
 * The analysis is a pure function of (title + description + seniority), all of
 * which are captured in the run's input hash — so identical inputs are analysed
 * once ever and reused for anyone, with no AI call.
 */
class JdAnalyzeStage extends Stage
{
    public function key(): string
    {
        return 'analyze';
    }

    public function label(): string
    {
        return 'Analyzing the job description';
    }

    public function iconPath(): string
    {
        return 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v4.5m3-6.75v6.75';
    }

    public function weight(): int
    {
        return 4;
    }

    public function run(ToolRun $run): StageResult
    {
        // Global reuse: the same title + description + level always yields the
        // same analysis, and the input hash already covers all three.
        if ($reuse = $this->reusableResult($run)) {
            $this->write($run, ['jd_result' => $reuse['jd_result']]);

            return new StageResult(
                provider: 'reused',
                model: 'existing analysis (run #'.$reuse['from'].')',
                tokensTotal: 0,
                payload: ToolSettings::storeRawPayloads() ? ['reused_from_run' => $reuse['from']] : [],
                isAiCall: false,
            );
        }

        $input = (array) $run->input_data;
        $resolved = JdSettings::resolvedFor($run->tool);
        $apiKey = (string) (AiSetting::current()->{$resolved['provider'].'_api_key'} ?? '');
        $inherited = ToolSettings::aiProvider($run->tool, 'analyze') === ''
            && ToolSettings::aiModel($run->tool, 'analyze') === '';

        $result = app(JdAnalyzer::class)->analyze(
            (string) ($input['title'] ?? ''),
            (string) ($input['description'] ?? ''),
            (string) ($input['seniority'] ?? ''),
            $resolved['provider'],
            $resolved['model'],
            $resolved['temperature'],
            $resolved['max_tokens'],
            $apiKey,
        );

        $this->write($run, ['jd_result' => $result->toArray()]);

        return new StageResult(
            provider: $result->provider,
            model: $result->model,
            providerInherited: $inherited,
            tokensTotal: $result->tokensUsed,
            payload: ToolSettings::storeRawPayloads()
                ? ['request' => ['title' => $input['title'] ?? '', 'seniority' => $input['seniority'] ?? ''], 'response' => $result->rawResponse]
                : [],
            isAiCall: true,
        );
    }

    /**
     * A prior analysis of the exact same inputs, from any run that still holds
     * one. A row that still exists has not been purged, so "found" means "not
     * deleted". Newest wins.
     *
     * @return array{jd_result: array<string, mixed>, from: int}|null
     */
    private function reusableResult(ToolRun $run): ?array
    {
        if (blank($run->input_hash)) {
            return null;
        }

        $priors = ToolRun::query()
            ->where('tool', $run->tool)
            ->where('input_hash', $run->input_hash)
            ->where('id', '!=', $run->id)
            ->latest('id')
            ->limit(10)
            ->get(['id', 'output']);

        foreach ($priors as $prior) {
            $jd = (array) (((array) $prior->output)['jd_result'] ?? []);

            if ($jd !== [] && isset($jd['score'])) {
                return ['jd_result' => $jd, 'from' => (int) $prior->id];
            }
        }

        return null;
    }
}
