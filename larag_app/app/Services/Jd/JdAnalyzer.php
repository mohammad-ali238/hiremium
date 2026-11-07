<?php

namespace App\Services\Jd;

use App\Models\AiSetting;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\FakePipelineAi;
use App\Support\ProviderHttpClient;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\Role;
use Throwable;

/**
 * Analyses one job description in a SINGLE AI call.
 *
 * The one place all job-description analysis happens — the anonymous free tool
 * now, and logged-in callers later. Everything it needs is passed in (provider,
 * model, temperature, max-tokens, key), so a caller supplies its own
 * configuration without this service reaching into settings or billing.
 *
 * Text only: there is no file and no vision path.
 */
class JdAnalyzer
{
    /**
     * @param  string  $seniority  A value from SeniorityLevels::LEVELS.
     * @param  array<string, mixed>  $context  Reserved; unused today.
     */
    public function analyze(
        string $title,
        string $description,
        string $seniority,
        string $provider,
        string $model,
        float $temperature,
        int $maxTokens,
        string $apiKey,
        array $context = [],
    ): JdResult {
        if (FakePipelineAi::enabled()) {
            return FakePipelineAi::jd($title, $seniority);
        }

        if (trim($apiKey) === '') {
            throw new AiProviderException(ucfirst($provider).' API key is not configured.');
        }

        $userMessage = JdPrompt::userText($title, $description, $seniority);

        return $provider === AiSetting::PROVIDER_GEMINI
            ? $this->withGemini($userMessage, $model, $temperature, $maxTokens, $apiKey)
            : $this->withOpenAi($userMessage, $model, $temperature, $maxTokens, $apiKey);
    }

    private function withOpenAi(string $userMessage, string $model, float $temperature, int $maxTokens, string $apiKey): JdResult
    {
        $client = ProviderHttpClient::openai($apiKey);

        try {
            $response = $client->chat()->create([
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => JdPrompt::system()],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI job-description request failed: '.$e->getMessage(), 0, $e);
        }

        $parsed = json_decode($response->choices[0]->message->content ?? '', true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new AiProviderException('OpenAI returned an unparseable job-description response.');
        }

        return JdResult::normalize($parsed, AiSetting::PROVIDER_OPENAI, $model, $response->usage?->totalTokens);
    }

    private function withGemini(string $userMessage, string $model, float $temperature, int $maxTokens, string $apiKey): JdResult
    {
        $client = ProviderHttpClient::gemini($apiKey);

        try {
            $response = $client->generativeModel($model)
                ->withSystemInstruction(Content::parse(JdPrompt::system(), Role::USER))
                ->withGenerationConfig(new GenerationConfig(
                    maxOutputTokens: $maxTokens,
                    temperature: $temperature,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                ))
                ->generateContent($userMessage);
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini job-description request failed: '.$e->getMessage(), 0, $e);
        }

        $parsed = json_decode($response->text() ?? '', true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new AiProviderException('Gemini returned an unparseable job-description response.');
        }

        return JdResult::normalize($parsed, AiSetting::PROVIDER_GEMINI, $model, $response->usageMetadata?->totalTokenCount ?? null);
    }
}
