<?php

namespace App\Services\ResumeParsing\Providers;

use App\Models\AiSetting;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\Contracts\ResumeParsingProvider;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\ResumeParsing\ParsedResume;
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

class GeminiResumeProvider implements ResumeParsingProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    private function sanitizeUtf8(string $string): string
    {
        // Converts invalid UTF-8 sequences to the replacement character '?'
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }

    public function parse(ProcessedFile $file, AiSetting $settings): ParsedResume
    {
        if (empty($settings->gemini_api_key)) {
            throw new AiProviderException('Gemini API key is not configured.');
        }

        $client = ProviderHttpClient::gemini($settings->gemini_api_key);
        $model = $settings->parsing_gemini_model;
        // The confidence contract rides along with the admin's saved prompt, so
        // every parse reports how certain the model is about what it read.
        $system = $settings->parsing_system_prompt.ParseConfidence::promptAddendum($settings->parsing_system_prompt);

        $generationConfig = new GenerationConfig(
            temperature: (float) $settings->parsing_temperature,
            responseMimeType: ResponseMimeType::APPLICATION_JSON,
        );

        $userContent = $file->isText()
            ? Content::parse("Resume text:\n\n".$this->sanitizeUtf8($file->content), Role::USER)
            : $this->buildBinaryContent($file);

        try {
            $response = $client->generativeModel($model)
                ->withSystemInstruction(Content::parse($system, Role::USER))
                ->withGenerationConfig($generationConfig)
                ->generateContent($userContent);
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini parsing request failed: '.$e->getMessage(), 0, $e);
        }

        $text = $response->text() ?? '';
        $parsed = json_decode($text, true);

        if (! is_array($parsed)) {
            throw new AiProviderException('Gemini returned an unparseable response.');
        }

        return new ParsedResume(
            data: ParsedResume::normalize($parsed),
            tokensUsed: $response->usageMetadata?->totalTokenCount ?? null,
            provider: $this->name(),
            model: $model,
            rawResponse: method_exists($response, 'toArray') ? $response->toArray() : (array) $response,
        );
    }

    private function buildBinaryContent(ProcessedFile $file): Content
    {
        $bytes = @file_get_contents($file->content);
        if ($bytes === false) {
            throw new AiProviderException('Could not read uploaded file from temp storage.');
        }

        $mimeEnum = MimeType::tryFrom($file->mime);
        if (! $mimeEnum) {
            throw new AiProviderException("Gemini does not support the MIME type '{$file->mime}'. Supported: PNG, JPEG, WebP, PDF.");
        }

        return new Content(
            parts: [
                new Part(text: 'Parse this resume into the JSON schema described in the system prompt.'),
                new Part(inlineData: new Blob(
                    mimeType: $mimeEnum,
                    data: base64_encode($bytes),
                )),
            ],
            role: Role::USER,
        );
    }
}
