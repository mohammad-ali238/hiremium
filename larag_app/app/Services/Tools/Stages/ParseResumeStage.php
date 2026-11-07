<?php

namespace App\Services\Tools\Stages;

use App\Models\ToolRun;
use App\Services\Ai\FakePipelineAi;
use App\Services\ResumeParsing\ProcessedFile;
use App\Services\ResumeParsing\ResumeParserManager;
use App\Services\Tools\Contracts\StageResult;
use App\Services\Tools\ToolAi;
use App\Support\ToolSettings;

/**
 * Stage 2 — the first AI call: turn the CV into the structured resume the app
 * uses everywhere (name, title, skills, experience, education…).
 *
 * The provider and model come from ToolAi, so an admin can pin this tool's
 * parsing to a cheaper model than the recruiter pipeline uses; everything else
 * (API keys, prompts, temperature) still comes from AI Settings.
 */
class ParseResumeStage extends Stage
{
    public function key(): string
    {
        return 'parse';
    }

    public function label(): string
    {
        return 'Reading your resume';
    }

    public function iconPath(): string
    {
        return 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25';
    }

    public function weight(): int
    {
        return 3;
    }

    public function run(ToolRun $run): StageResult
    {
        // The same file yields the same parse — it depends on the CV bytes, not
        // the job. If this exact file was already parsed for this same address,
        // reuse that result and skip the AI call; only the match stage, which is
        // job-specific, still has to run. Scoped to the canonical email on
        // purpose: a stranger who happens to upload an identical file gets their
        // own parse, never one produced from someone else's submission.
        if ($reuse = $this->reusableParse($run)) {
            $this->write($run, ['candidate' => $reuse['candidate']]);

            return new StageResult(
                provider: 'reused',
                model: 'existing parse (run #'.$reuse['from'].')',
                tokensTotal: 0,
                payload: ToolSettings::storeRawPayloads() ? ['reused_from_run' => $reuse['from']] : [],
                isAiCall: false,
            );
        }

        $processed = ExtractTextStage::processedFor($run);
        $resolved = ToolAi::resolve($run->tool, 'parse');

        try {
            $parsed = FakePipelineAi::enabled()
                ? FakePipelineAi::parse($processed, $processed->filename)
                : $this->parse($run, $processed);
        } finally {
            // A vision run wrote the file back to disk for this request only.
            if ($processed->isImage()) {
                @unlink($processed->content);
            }
        }

        $this->write($run, ['candidate' => $parsed->data]);

        return new StageResult(
            provider: $parsed->provider,
            model: $parsed->model,
            providerInherited: $resolved['inherited'],
            tokensTotal: $parsed->tokensUsed,
            payload: ToolSettings::storeRawPayloads()
                ? ['request' => $processed->metaForLog(), 'response' => $parsed->rawResponse]
                : [],
            isAiCall: true,
        );
    }

    /**
     * A prior parse of this exact file by this exact address, if one survives.
     *
     * A row that still exists has not been purged, so "found" already means
     * "not deleted". The newest match wins, so a re-parse (say the model was
     * changed and the run re-run) is preferred over an older one.
     *
     * @return array{candidate: array<string, mixed>, from: int}|null
     */
    private function reusableParse(ToolRun $run): ?array
    {
        if (blank($run->file_hash) || blank($run->email_canonical)) {
            return null;
        }

        $priors = ToolRun::query()
            ->where('tool', $run->tool)
            ->where('email_canonical', $run->email_canonical)
            ->where('file_hash', $run->file_hash)
            ->where('id', '!=', $run->id)
            ->latest('id')
            ->limit(10)
            ->get(['id', 'output']);

        foreach ($priors as $prior) {
            $candidate = (array) (((array) $prior->output)['candidate'] ?? []);

            if ($candidate !== []) {
                return ['candidate' => $candidate, 'from' => (int) $prior->id];
            }
        }

        return null;
    }

    private function parse(ToolRun $run, ProcessedFile $processed)
    {
        // The tool's own provider/model, applied to a replica of AI Settings.
        $settings = ToolAi::settingsFor($run->tool, 'parse');

        return app(ResumeParserManager::class)
            ->provider($settings->parsing_ai_provider)
            ->parse($processed, $settings);
    }
}
