<?php

namespace App\Services\Tools\Stages;

use App\Models\AiSetting;
use App\Models\ToolRun;
use App\Services\Experience\ExperienceProfileExtractor;
use App\Services\Tools\Contracts\StageResult;
use App\Support\ExperienceSettings;
use App\Support\ToolSettings;

/**
 * The second AI call of the Resume Experience Extractor: the judgement calls
 * the report shows — industry, the seniority this person operates at, and a
 * short "about this candidate" written from the whole CV.
 *
 * The role list and every duration on the report are NOT asked for here: they
 * are computed from the parsed dates, so they are consistent run to run.
 *
 * The parse stage has already run, so this call is text-only: on a scanned CV
 * vision was already paid for once, and reading the image a second time would
 * double the cost of the tool for no extra signal.
 */
class ExtractExperienceStage extends Stage
{
    public function key(): string
    {
        return ExperienceSettings::STAGE;
    }

    public function label(): string
    {
        return 'Reading the career history';
    }

    public function iconPath(): string
    {
        // Briefcase — a career history.
        return 'M20.25 14.15v4.25c0 1.114-.848 2.062-1.96 2.153-.837.07-1.68.117-2.53.14a3 3 0 01-2.56 1.307h-2.4a3 3 0 01-2.56-1.307c-.85-.023-1.693-.07-2.53-.14A2.108 2.108 0 013.75 18.4v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z';
    }

    public function weight(): int
    {
        return 3;
    }

    public function run(ToolRun $run): StageResult
    {
        // The profile is a pure function of the file bytes, so an identical CV
        // is profiled once ever — a prior run's result is reused for anyone.
        if ($reuse = $this->reusableResult($run)) {
            $this->write($run, ['experience_profile' => $reuse['experience_profile']]);

            return new StageResult(
                provider: 'reused',
                model: 'existing experience profile (run #'.$reuse['from'].')',
                tokensTotal: 0,
                payload: ToolSettings::storeRawPayloads() ? ['reused_from_run' => $reuse['from']] : [],
                isAiCall: false,
            );
        }

        $resolved = ExperienceSettings::resolvedFor($run->tool);
        $apiKey = (string) (AiSetting::current()->{$resolved['provider'].'_api_key'} ?? '');
        $inherited = ToolSettings::aiProvider($run->tool, $this->key()) === ''
            && ToolSettings::aiModel($run->tool, $this->key()) === '';

        $resumeText = $this->resumeText($run);

        $result = app(ExperienceProfileExtractor::class)->extract(
            $resumeText,
            $resolved['provider'],
            $resolved['model'],
            $resolved['temperature'],
            $resolved['max_tokens'],
            $apiKey,
        );

        $this->write($run, ['experience_profile' => $result->toArray()]);

        return new StageResult(
            provider: $result->provider,
            model: $result->model,
            providerInherited: $inherited,
            tokensTotal: $result->tokensUsed,
            payload: ToolSettings::storeRawPayloads()
                ? ['request' => ['chars' => mb_strlen($resumeText)], 'response' => $result->rawResponse]
                : [],
            isAiCall: true,
        );
    }

    /**
     * What the model reads.
     *
     * The extracted text when there is any. A scanned CV has none — the parse
     * stage read it with vision — so fall back to the structured candidate the
     * parse produced, which carries the same facts in fewer tokens.
     */
    private function resumeText(ToolRun $run): string
    {
        $text = trim((string) $this->read($run, 'resume_text', ''));

        if ($text !== '') {
            return $text;
        }

        $candidate = (array) $this->read($run, 'candidate', []);

        return $candidate === []
            ? ''
            : (string) json_encode($candidate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A prior profile of the same file bytes, from any run that still holds
     * one. A row that still exists has not been purged, so "found" means "not
     * deleted". Newest wins.
     *
     * @return array{experience_profile: array<string, mixed>, from: int}|null
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
            $profile = (array) (((array) $prior->output)['experience_profile'] ?? []);

            if ($profile !== [] && isset($profile['about'])) {
                return ['experience_profile' => $profile, 'from' => (int) $prior->id];
            }
        }

        return null;
    }
}
