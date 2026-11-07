<?php

namespace App\Services\Tools\Stages;

use App\Models\AiSetting;
use App\Models\ToolRun;
use App\Services\Skills\SkillProfileExtractor;
use App\Services\Tools\Contracts\StageResult;
use App\Support\SkillsSettings;
use App\Support\ToolSettings;

/**
 * The second AI call of the Resume Skills Extractor: turn the resume into the
 * profile the report shows — industry, core and soft skills, and a short
 * "about this candidate" written from the whole CV.
 *
 * The parse stage has already run, so this call is text-only: on a scanned CV
 * vision was already paid for once, and reading the image a second time would
 * double the cost of the tool for no extra signal.
 */
class ExtractSkillsStage extends Stage
{
    public function key(): string
    {
        return SkillsSettings::STAGE;
    }

    public function label(): string
    {
        return 'Extracting skills';
    }

    public function iconPath(): string
    {
        return 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z';
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
            $this->write($run, ['skill_profile' => $reuse['skill_profile']]);

            return new StageResult(
                provider: 'reused',
                model: 'existing skills profile (run #'.$reuse['from'].')',
                tokensTotal: 0,
                payload: ToolSettings::storeRawPayloads() ? ['reused_from_run' => $reuse['from']] : [],
                isAiCall: false,
            );
        }

        $resolved = SkillsSettings::resolvedFor($run->tool);
        $apiKey = (string) (AiSetting::current()->{$resolved['provider'].'_api_key'} ?? '');
        $inherited = ToolSettings::aiProvider($run->tool, $this->key()) === ''
            && ToolSettings::aiModel($run->tool, $this->key()) === '';

        $resumeText = $this->resumeText($run);

        $result = app(SkillProfileExtractor::class)->extract(
            $resumeText,
            $resolved['provider'],
            $resolved['model'],
            $resolved['temperature'],
            $resolved['max_tokens'],
            $apiKey,
        );

        $this->write($run, ['skill_profile' => $result->toArray()]);

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
     * @return array{skill_profile: array<string, mixed>, from: int}|null
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
            $profile = (array) (((array) $prior->output)['skill_profile'] ?? []);

            if ($profile !== [] && isset($profile['about'])) {
                return ['skill_profile' => $profile, 'from' => (int) $prior->id];
            }
        }

        return null;
    }
}
