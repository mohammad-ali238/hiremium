<?php

namespace App\Services\ResumeParsing\Providers;

use App\Models\AiSetting;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\Contracts\ResumeParsingProvider;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\ResumeParsing\ParsedResume;
use App\Services\ResumeParsing\ProcessedFile;
use App\Support\ProviderHttpClient;
use Throwable;

class OpenAiResumeProvider implements ResumeParsingProvider
{
    public function name(): string
    {
        return 'openai';
    }

    private function sanitizeUtf8(string $string): string
    {
        // Converts invalid UTF-8 sequences to the replacement character '?'
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }

    public function parse(ProcessedFile $file, AiSetting $settings): ParsedResume
    {
        if (empty($settings->openai_api_key)) {
            throw new AiProviderException('OpenAI API key is not configured.');
        }

        $client = ProviderHttpClient::openai($settings->openai_api_key);
        $model = $settings->parsing_openai_model;
        // The confidence contract rides along with the admin's saved prompt, so
        // every parse reports how certain the model is about what it read.
        $system = $settings->parsing_system_prompt.ParseConfidence::promptAddendum($settings->parsing_system_prompt);

        $userContent = $file->isText()
            ? "Resume text:\n\n" . $this->sanitizeUtf8($file->content)
            : $this->buildBinaryContent($file);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ];

        try {
            $response = $client->chat()->create([
                'model' => $model,
                'temperature' => (float) $settings->parsing_temperature,
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ]);
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI parsing request failed: '.$e->getMessage(), 0, $e);
        }

        $content = $response->choices[0]->message->content ?? '';
        $parsed = json_decode($content, true);

        if (! is_array($parsed)) {
            throw new AiProviderException('OpenAI returned an unparseable response.');
        }

        return new ParsedResume(
            data: ParsedResume::normalize($parsed),
            tokensUsed: $response->usage?->totalTokens,
            provider: $this->name(),
            model: $model,
            rawResponse: $response->toArray(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBinaryContent(ProcessedFile $file): array
    {
        $bytes = @file_get_contents($file->content);
        if ($bytes === false) {
            throw new AiProviderException('Could not read uploaded file from temp storage.');
        }
        $base64 = base64_encode($bytes);
        $mime = $file->mime;

        $textPart = ['type' => 'text', 'text' => 'Parse this resume into the JSON schema described in the system prompt.'];

        if ($mime === 'application/pdf') {
            return [
                $textPart,
                [
                    'type' => 'file',
                    'file' => [
                        'filename' => $this->sanitizeUtf8($file->filename),
                        'file_data' => 'data:application/pdf;base64,'.$base64,
                    ],
                ],
            ];
        }

        // Image (jpg/png/webp/gif)
        return [
            $textPart,
            [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$mime.';base64,'.$base64],
            ],
        ];
    }
}
