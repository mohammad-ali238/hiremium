<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use App\Services\Ai\Contracts\AiScoringProvider;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;

class AiServiceManager
{
    public function provider(?string $name = null): AiScoringProvider
    {
        $name ??= AiSetting::current()->default_provider;

        return match ($name) {
            AiSetting::PROVIDER_OPENAI => new OpenAiProvider,
            AiSetting::PROVIDER_GEMINI => new GeminiProvider,
            default => throw new AiProviderException("Unknown AI provider: {$name}"),
        };
    }

    public function score(string $jobText, string $applicationText): AiScoreResult
    {
        $settings = AiSetting::current();

        return $this->provider($settings->default_provider)
            ->score($jobText, $applicationText, $settings);
    }

    /**
     * Same single call as score() — every lane now gets the full findings set
     * (MatchPrompt) and runs them through MatchRubric. Kept as its own name
     * because that is what the pipeline, the tools and the API ask for.
     */
    public function scoreDetailed(string $jobText, string $applicationText): AiScoreResult
    {
        return $this->score($jobText, $applicationText);
    }
}
