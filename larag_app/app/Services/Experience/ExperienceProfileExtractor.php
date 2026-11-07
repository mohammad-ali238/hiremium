<?php

namespace App\Services\Experience;

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
 * Builds a candidate's experience profile in a SINGLE AI call.
 *
 * Everything it needs is passed in (provider, model, temperature, max-tokens,
 * key), so a caller supplies its own configuration without this service
 * reaching into settings or billing — the same shape as JdAnalyzer.
 *
 * Text only, and deliberately so: the parse stage has already run and has
 * already paid for vision on a scanned CV, so this call reads the text (or the
 * parsed candidate) rather than the image a second time.
 */
class ExperienceProfileExtractor
{
    public function extract(
        string $resumeText,
        string $provider,
        string $model,
        float $temperature,
        int $maxTokens,
        string $apiKey,
    ): ExperienceProfileResult {
        if (FakePipelineAi::enabled()) {
            return FakePipelineAi::experienceProfile($resumeText);
        }

        if (trim($apiKey) === '') {
            throw new AiProviderException(ucfirst($provider).' API key is not configured.');
        }

        $userMessage = ExperienceProfilePrompt::userText($resumeText);

        return $provider === AiSetting::PROVIDER_GEMINI
            ? $this->withGemini($userMessage, $model, $temperature, $maxTokens, $apiKey)
            : $this->withOpenAi($userMessage, $model, $temperature, $maxTokens, $apiKey);
    }

    private function withOpenAi(string $userMessage, string $model, float $temperature, int $maxTokens, string $apiKey): ExperienceProfileResult
    {
        $client = ProviderHttpClient::openai($apiKey);

        try {
            $response = $client->chat()->create([
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => ExperienceProfilePrompt::system()],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI experience request failed: '.$e->getMessage(), 0, $e);
        }

        return self::normalize(
            $response->choices[0]->message->content ?? '',
            AiSetting::PROVIDER_OPENAI,
            $model,
            $response->usage?->totalTokens,
        );
    }

    private function withGemini(string $userMessage, string $model, float $temperature, int $maxTokens, string $apiKey): ExperienceProfileResult
    {
        $client = ProviderHttpClient::gemini($apiKey);

        try {
            $response = $client->generativeModel($model)
                ->withSystemInstruction(Content::parse(ExperienceProfilePrompt::system(), Role::USER))
                ->withGenerationConfig(new GenerationConfig(
                    maxOutputTokens: $maxTokens,
                    temperature: $temperature,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                ))
                ->generateContent($userMessage);
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini experience request failed: '.$e->getMessage(), 0, $e);
        }

        return self::normalize(
            $response->text() ?? '',
            AiSetting::PROVIDER_GEMINI,
            $model,
            $response->usageMetadata?->totalTokenCount ?? null,
        );
    }

    private static function normalize(string $content, string $provider, string $model, ?int $tokens): ExperienceProfileResult
    {
        $parsed = json_decode($content, true);

        // Any of the four rendered fields is enough to call the response usable;
        // a CV with no soft skills listed is normal, an empty object is not.
        $usable = is_array($parsed) && (
            isset($parsed['about']) || isset($parsed['seniority']) || isset($parsed['industry'])
        );

        if (! $usable) {
            throw new AiProviderException(ucfirst($provider).' returned an unparseable experience response.');
        }

        return ExperienceProfileResult::normalize($parsed, $provider, $model, $tokens);
    }
}
