<?php

namespace App\Services\Ats;

use App\Models\AiSetting;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\FakePipelineAi;
use App\Services\ResumeParsing\ProcessedFile;
use App\Support\ProviderHttpClient;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Part;
use Gemini\Enums\MimeType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\Role;
use Throwable;

/**
 * Scores one resume for ATS quality in a SINGLE AI call.
 *
 * The one place all ATS scoring happens — the anonymous free tool now, and
 * logged-in single/bulk callers later. Everything it needs is passed in
 * (provider, model, temperature, max-tokens, key), so a caller supplies its own
 * configuration without this service reaching into settings or billing.
 *
 * A resume with extractable text is sent as text; an image/scanned document is
 * sent to the same prompt as an inline image (still one call — no parse step).
 */
class AtsScorer
{
    /**
     * @param  array<string, mixed>  $context  Reserved (e.g. target_role); unused today.
     */
    public function score(
        ProcessedFile $file,
        string $provider,
        string $model,
        float $temperature,
        int $maxTokens,
        string $apiKey,
        array $context = [],
    ): AtsScoreResult {
        if (FakePipelineAi::enabled()) {
            return FakePipelineAi::ats($file);
        }

        if (trim($apiKey) === '') {
            throw new AiProviderException(ucfirst($provider).' API key is not configured.');
        }

        return $provider === AiSetting::PROVIDER_GEMINI
            ? $this->scoreWithGemini($file, $model, $temperature, $maxTokens, $apiKey)
            : $this->scoreWithOpenAi($file, $model, $temperature, $maxTokens, $apiKey);
    }

    private function scoreWithOpenAi(ProcessedFile $file, string $model, float $temperature, int $maxTokens, string $apiKey): AtsScoreResult
    {
        $client = ProviderHttpClient::openai($apiKey);

        $userContent = $file->isText()
            ? AtsPrompt::userText($file->content)
            : $this->openAiBinaryContent($file);

        try {
            $response = $client->chat()->create([
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => AtsPrompt::system()],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI ATS request failed: '.$e->getMessage(), 0, $e);
        }

        $parsed = json_decode($response->choices[0]->message->content ?? '', true);

        if (! is_array($parsed) || ! isset($parsed['scores'])) {
            throw new AiProviderException('OpenAI returned an unparseable ATS response.');
        }

        return AtsScoreResult::normalize($parsed, AiSetting::PROVIDER_OPENAI, $model, $response->usage?->totalTokens);
    }

    private function scoreWithGemini(ProcessedFile $file, string $model, float $temperature, int $maxTokens, string $apiKey): AtsScoreResult
    {
        $client = ProviderHttpClient::gemini($apiKey);

        $userContent = $file->isText()
            ? Content::parse(AtsPrompt::userText($file->content), Role::USER)
            : $this->geminiBinaryContent($file);

        try {
            $response = $client->generativeModel($model)
                ->withSystemInstruction(Content::parse(AtsPrompt::system(), Role::USER))
                ->withGenerationConfig(new GenerationConfig(
                    maxOutputTokens: $maxTokens,
                    temperature: $temperature,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                ))
                ->generateContent($userContent);
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini ATS request failed: '.$e->getMessage(), 0, $e);
        }

        $parsed = json_decode($response->text() ?? '', true);

        if (! is_array($parsed) || ! isset($parsed['scores'])) {
            throw new AiProviderException('Gemini returned an unparseable ATS response.');
        }

        return AtsScoreResult::normalize($parsed, AiSetting::PROVIDER_GEMINI, $model, $response->usageMetadata?->totalTokenCount ?? null);
    }

    /**
     * OpenAI multimodal content for an image / scanned PDF, mirroring the resume
     * parser: a PDF goes as a `file` part, an image as `image_url`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function openAiBinaryContent(ProcessedFile $file): array
    {
        $base64 = base64_encode($this->readBytes($file));
        $textPart = ['type' => 'text', 'text' => AtsPrompt::userImageInstruction()];

        if ($file->mime === 'application/pdf') {
            return [
                $textPart,
                ['type' => 'file', 'file' => [
                    'filename' => $file->filename,
                    'file_data' => 'data:application/pdf;base64,'.$base64,
                ]],
            ];
        }

        return [
            $textPart,
            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$file->mime.';base64,'.$base64]],
        ];
    }

    private function geminiBinaryContent(ProcessedFile $file): Content
    {
        $mimeEnum = MimeType::tryFrom($file->mime);

        if (! $mimeEnum) {
            throw new AiProviderException("Gemini does not support the MIME type '{$file->mime}'. Supported: PNG, JPEG, WebP, PDF.");
        }

        return new Content(
            parts: [
                new Part(text: AtsPrompt::userImageInstruction()),
                new Part(inlineData: new Blob(mimeType: $mimeEnum, data: base64_encode($this->readBytes($file)))),
            ],
            role: Role::USER,
        );
    }

    private function readBytes(ProcessedFile $file): string
    {
        $bytes = @file_get_contents($file->content);

        if ($bytes === false) {
            throw new AiProviderException('Could not read the uploaded file for ATS scoring.');
        }

        return $bytes;
    }
}
