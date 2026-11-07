<?php

namespace App\Services\Ai\Providers;

use App\Models\AiSetting;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\Contracts\AiScoringProvider;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\MatchPrompt;
use App\Support\ProviderHttpClient;
use Throwable;

class OpenAiProvider implements AiScoringProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function score(string $jobText, string $applicationText, AiSetting $settings): AiScoreResult
    {
        if (empty($settings->openai_api_key)) {
            throw new AiProviderException('OpenAI API key is not configured.');
        }

        $client = ProviderHttpClient::openai($settings->openai_api_key);

        // Fixed in code for every lane — the admin cannot edit the matching prompt.
        $systemPrompt = MatchPrompt::system();

        $userMessage = "JOB:\n{$jobText}\n\n---\nAPPLICATION:\n{$applicationText}";

        try {
            $response = $client->chat()->create([
                'model' => $settings->default_model,
                'temperature' => (float) $settings->temperature,
                'max_tokens' => (int) $settings->max_tokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI request failed: '.$e->getMessage(), 0, $e);
        }

        $raw = $response->toArray();
        $content = $response->choices[0]->message->content ?? '';
        $parsed = json_decode($content, true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new AiProviderException('OpenAI returned an unparseable response.');
        }

        return new AiScoreResult(
            score: (int) max(0, min(100, $parsed['score'])),
            reason: (string) ($parsed['reason'] ?? ''),
            tokensUsed: $response->usage?->totalTokens,
            provider: $this->name(),
            model: $settings->default_model,
            rawResponse: $raw,
            strengths: array_values(array_filter(array_map('strval', (array) ($parsed['strengths'] ?? [])))),
            concerns: array_values(array_filter(array_map('strval', (array) ($parsed['concerns'] ?? [])))),
            // Findings only — MatchRubric turns these into the score, the
            // matched/missing skills and the must-have coverage.
            evidence: MatchPrompt::evidence($parsed),
        );
    }
}
