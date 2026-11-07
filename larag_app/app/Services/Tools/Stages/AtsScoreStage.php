<?php

namespace App\Services\Tools\Stages;

use App\Models\AiSetting;
use App\Models\ToolRun;
use App\Services\Ats\AtsScorer;
use App\Services\Tools\Contracts\StageResult;
use App\Support\AtsSettings;
use App\Support\ToolSettings;

/**
 * The one AI call of the ATS Score Checker: the extracted resume (or its image
 * for a scanned CV) is scored in a single request.
 *
 * An ATS score is a pure function of the file bytes, so an identical file is
 * scored once ever — a prior run's result is reused for anyone, no AI call.
 */
class AtsScoreStage extends Stage
{
    public function key(): string
    {
        return 'score';
    }

    public function label(): string
    {
        return 'Scoring your resume';
    }

    public function iconPath(): string
    {
        return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    }

    public function weight(): int
    {
        return 4;
    }

    public function run(ToolRun $run): StageResult
    {
        // Global reuse-by-hash: the same file always yields the same ATS result.
        if ($reuse = $this->reusableResult($run)) {
            $this->write($run, ['ats_result' => $reuse['ats_result']]);

            return new StageResult(
                provider: 'reused',
                model: 'existing ATS score (run #'.$reuse['from'].')',
                tokensTotal: 0,
                payload: ToolSettings::storeRawPayloads() ? ['reused_from_run' => $reuse['from']] : [],
                isAiCall: false,
            );
        }

        $resolved = AtsSettings::resolvedFor($run->tool);
        $apiKey = (string) (AiSetting::current()->{$resolved['provider'].'_api_key'} ?? '');
        $inherited = ToolSettings::aiProvider($run->tool, 'score') === ''
            && ToolSettings::aiModel($run->tool, 'score') === '';

        $file = ExtractTextStage::processedFor($run);

        try {
            $result = app(AtsScorer::class)->score(
                $file,
                $resolved['provider'],
                $resolved['model'],
                $resolved['temperature'],
                $resolved['max_tokens'],
                $apiKey,
            );
        } finally {
            // A vision run copied the original upload to a temp path for this request.
            if ($file->isImage()) {
                @unlink($file->content);
            }
        }

        $this->write($run, ['ats_result' => $result->toArray()]);

        return new StageResult(
            provider: $result->provider,
            model: $result->model,
            providerInherited: $inherited,
            tokensTotal: $result->tokensUsed,
            payload: ToolSettings::storeRawPayloads()
                ? ['request' => $file->metaForLog(), 'response' => $result->rawResponse]
                : [],
            isAiCall: true,
        );
    }

    /**
     * A prior ATS result for the same file bytes, from any run that still holds
     * one. A row that still exists has not been purged, so "found" means "not
     * deleted". Newest wins.
     *
     * @return array{ats_result: array<string, mixed>, from: int}|null
     */
    private function reusableResult(ToolRun $run): ?array
    {
        if (blank($run->file_hash)) {
            return null;
        }

        $priors = ToolRun::query()
            ->where('tool', $run->tool)
            ->where('file_hash', $run->file_hash)
            ->where('id', '!=', $run->id)
            ->latest('id')
            ->limit(10)
            ->get(['id', 'output']);

        foreach ($priors as $prior) {
            $ats = (array) (((array) $prior->output)['ats_result'] ?? []);

            if ($ats !== [] && isset($ats['score'])) {
                return ['ats_result' => $ats, 'from' => (int) $prior->id];
            }
        }

        return null;
    }
}
