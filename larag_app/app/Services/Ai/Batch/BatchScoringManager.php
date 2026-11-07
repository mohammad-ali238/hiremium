<?php

namespace App\Services\Ai\Batch;

use App\Models\AiSetting;
use App\Services\Ai\Batch\Contracts\BatchScoringDriver;
use App\Services\Ai\Batch\Drivers\GeminiBatchDriver;
use App\Services\Ai\Batch\Drivers\OpenAiBatchDriver;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\FakePipelineAi;

/**
 * Selects the async Batch API driver for a provider and decides whether the
 * batch lane can handle the current AI configuration.
 *
 * The batch lane runs one combined parse+score call per CV, so it can only
 * honour AI Settings when the parse-resume and match-score providers are the
 * SAME. Mixed-provider workspaces fall back to the two-call live/throttled lane.
 */
class BatchScoringManager
{
    /** Providers that currently have a batch driver. */
    private const SUPPORTED = [AiSetting::PROVIDER_OPENAI, AiSetting::PROVIDER_GEMINI];

    public function driver(?string $name = null): BatchScoringDriver
    {
        $name ??= AiSetting::current()->default_provider;

        return match ($name) {
            // Resolved from the container so a fake client can be injected in tests.
            AiSetting::PROVIDER_OPENAI => app(OpenAiBatchDriver::class),
            AiSetting::PROVIDER_GEMINI => app(GeminiBatchDriver::class),
            default => throw new AiProviderException("No batch driver for provider: {$name}"),
        };
    }

    public function supports(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED, true);
    }

    /**
     * Whether the batch lane can process this workspace's uploads faithfully:
     * parse + match use the same provider, that provider has a driver, and its
     * API key is configured. Anything else falls back to the live/throttled lane.
     */
    public function isEligible(AiSetting $settings): bool
    {
        // Testing mode must never hit a real provider Batch API — the overflow
        // takes the throttled live lane, where the fake AI handles it.
        if (FakePipelineAi::enabled()) {
            return false;
        }

        $provider = $settings->default_provider;

        return $settings->parsing_ai_provider === $provider
            && $this->supports($provider)
            && $this->hasKey($settings, $provider);
    }

    private function hasKey(AiSetting $settings, string $provider): bool
    {
        return match ($provider) {
            AiSetting::PROVIDER_OPENAI => ! empty($settings->openai_api_key),
            AiSetting::PROVIDER_GEMINI => ! empty($settings->gemini_api_key),
            default => false,
        };
    }
}
