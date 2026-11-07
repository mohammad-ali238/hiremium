<?php

namespace App\Services\Ai\Providers;

use App\Models\AiSetting;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\Contracts\AiScoringProvider;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\MatchPrompt;
use App\Support\ProviderHttpClient;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\Role;
use Throwable;

class GeminiProvider implements AiScoringProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function score(string $jobText, string $applicationText, AiSetting $settings): AiScoreResult
    {
        if (empty($settings->gemini_api_key)) {
            throw new AiProviderException('Gemini API key is not configured.');
        }

        $client = ProviderHttpClient::gemini($settings->gemini_api_key);

        // Fixed in code for every lane — the admin cannot edit the matching prompt.
        $systemPrompt = MatchPrompt::system();

        $userMessage = "JOB:\n{$jobText}\n\n---\nAPPLICATION:\n{$applicationText}";

        try {
            $response = $client->generativeModel($settings->default_model)
                ->withSystemInstruction(Content::parse($systemPrompt, Role::USER))
                ->withGenerationConfig(new GenerationConfig(
                    maxOutputTokens: (int) $settings->max_tokens,
                    temperature: (float) $settings->temperature,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                ))
                ->generateContent($userMessage);
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini request failed: '.$e->getMessage(), 0, $e);
        }

        $text = $response->text() ?? '';
        $parsed = json_decode($text, true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new AiProviderException('Gemini returned an unparseable response.');
        }

        $tokensUsed = $response->usageMetadata?->totalTokenCount ?? null;

        return new AiScoreResult(
            score: (int) max(0, min(100, $parsed['score'])),
            reason: (string) ($parsed['reason'] ?? ''),
            tokensUsed: $tokensUsed,
            provider: $this->name(),
            model: $settings->default_model,
            rawResponse: method_exists($response, 'toArray') ? $response->toArray() : (array) $response,
            strengths: array_values(array_filter(array_map('strval', (array) ($parsed['strengths'] ?? [])))),
            concerns: array_values(array_filter(array_map('strval', (array) ($parsed['concerns'] ?? [])))),
            // Findings only — MatchRubric turns these into the score, the
            // matched/missing skills and the must-have coverage.
            evidence: MatchPrompt::evidence($parsed),
        );
    }
}
