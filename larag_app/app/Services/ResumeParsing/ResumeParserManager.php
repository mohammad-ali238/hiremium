<?php

namespace App\Services\ResumeParsing;

use App\Models\AiSetting;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\Contracts\ResumeParsingProvider;
use App\Services\ResumeParsing\Providers\GeminiResumeProvider;
use App\Services\ResumeParsing\Providers\OpenAiResumeProvider;

class ResumeParserManager
{
    public function provider(?string $name = null): ResumeParsingProvider
    {
        $name ??= AiSetting::current()->parsing_ai_provider;

        return match ($name) {
            AiSetting::PROVIDER_OPENAI => new OpenAiResumeProvider,
            AiSetting::PROVIDER_GEMINI => new GeminiResumeProvider,
            default => throw new AiProviderException("Unknown parsing AI provider: {$name}"),
        };
    }

    public function parse(ProcessedFile $file): ParsedResume
    {
        $settings = AiSetting::current();

        return $this->provider($settings->parsing_ai_provider)
            ->parse($file, $settings);
    }
}
