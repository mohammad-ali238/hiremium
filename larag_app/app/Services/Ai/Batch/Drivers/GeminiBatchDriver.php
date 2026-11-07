<?php

namespace App\Services\Ai\Batch\Drivers;

use App\Models\AiSetting;
use App\Models\ProviderBatch;
use App\Services\Ai\Batch\BatchResultItem;
use App\Services\Ai\Batch\Contracts\BatchScoringDriver;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Support\ProviderHttpClient;
use GuzzleHttp\Client as GuzzleClient;
use Throwable;

/**
 * Gemini Batch Mode driver via raw REST — the google-gemini-php SDK has no
 * batch generation, so we call generativelanguage.googleapis.com directly.
 *
 * Inline mode: all requests are submitted in one batchGenerateContent call and
 * their results come back embedded in the long-running operation. Each request
 * is tagged with metadata.key = the custom id so results map back to CVs.
 */
class GeminiBatchDriver implements BatchScoringDriver
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(private ?GuzzleClient $http = null, private ?string $baseUrl = null) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function submit(array $items, AiSetting $settings): array
    {
        $requests = [];
        foreach ($items as $item) {
            $requests[] = [
                'request' => [
                    'system_instruction' => ['parts' => [['text' => $item->systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $item->userMessage]]]],
                    'generation_config' => [
                        'temperature' => $item->temperature,
                        'maxOutputTokens' => $item->maxTokens,
                        'responseMimeType' => 'application/json',
                    ],
                ],
                'metadata' => ['key' => $item->customId],
            ];
        }

        $model = $settings->default_model;
        $body = [
            'batch' => [
                'display_name' => 'hiremium-batch',
                'input_config' => ['requests' => ['requests' => $requests]],
            ],
        ];

        try {
            $data = $this->request($settings, 'POST', "/models/{$model}:batchGenerateContent", $body);
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini batch submit failed: '.$e->getMessage(), 0, $e);
        }

        $name = $data['name'] ?? null;
        if (! $name) {
            throw new AiProviderException('Gemini batch submit returned no operation name.');
        }

        return ['provider_batch_id' => (string) $name, 'input_file_id' => null];
    }

    public function poll(ProviderBatch $batch, AiSetting $settings): array
    {
        try {
            $op = $this->request($settings, 'GET', '/'.ltrim($batch->provider_batch_id, '/'));
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini batch poll failed: '.$e->getMessage(), 0, $e);
        }

        $stats = $op['metadata']['batchStats'] ?? [];

        return [
            'status' => $this->mapStatus($op),
            'output_file_id' => null,
            'completed' => (int) ($stats['successfulRequestCount'] ?? $stats['completedRequestCount'] ?? 0),
            'failed' => (int) ($stats['failedRequestCount'] ?? 0),
            'error' => $op['error']['message'] ?? null,
        ];
    }

    public function results(ProviderBatch $batch, AiSetting $settings): array
    {
        try {
            $op = $this->request($settings, 'GET', '/'.ltrim($batch->provider_batch_id, '/'));
        } catch (Throwable $e) {
            throw new AiProviderException('Gemini batch results fetch failed: '.$e->getMessage(), 0, $e);
        }

        $out = [];
        foreach ($this->inlinedResponses($op) as $entry) {
            $key = $entry['metadata']['key'] ?? ($entry['key'] ?? null);
            if (! $key) {
                continue;
            }

            $error = $entry['error']['message'] ?? ($entry['response']['error']['message'] ?? null);
            $content = $this->extractText($entry['response'] ?? []);

            $out[] = new BatchResultItem(
                customId: (string) $key,
                ok: $error === null && $content !== '',
                content: $content,
                error: $error,
            );
        }

        return $out;
    }

    /** Locate the array of per-request responses across the shapes Gemini may return. */
    private function inlinedResponses(array $op): array
    {
        $response = $op['response'] ?? [];

        return $response['inlinedResponses']['inlinedResponses']
            ?? $response['inlinedResponses']
            ?? $response['inlined_responses']
            ?? [];
    }

    /** Concatenate the text parts of a single generateContent response. */
    private function extractText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            $text .= $part['text'] ?? '';
        }

        return trim($text);
    }

    /** Map a Gemini operation to a ProviderBatch status. */
    private function mapStatus(array $op): string
    {
        $state = $op['metadata']['state'] ?? '';

        if (in_array($state, ['BATCH_STATE_FAILED', 'BATCH_STATE_CANCELLED', 'BATCH_STATE_EXPIRED'], true)) {
            return str_contains($state, 'CANCELLED') ? ProviderBatch::STATUS_CANCELLED : ProviderBatch::STATUS_FAILED;
        }

        if (! empty($op['done'])) {
            return isset($op['error']) ? ProviderBatch::STATUS_FAILED : ProviderBatch::STATUS_COMPLETED;
        }

        return ProviderBatch::STATUS_PROCESSING;
    }

    private function request(AiSetting $settings, string $method, string $path, ?array $json = null): array
    {
        if (empty($settings->gemini_api_key)) {
            throw new AiProviderException('Gemini API key is not configured.');
        }

        $options = [
            'headers' => [
                'x-goog-api-key' => $settings->gemini_api_key,
                'Content-Type' => 'application/json',
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->http()->request($method, $this->url($path), $options);

        return json_decode((string) $response->getBody(), true) ?: [];
    }

    private function url(string $path): string
    {
        return ($this->baseUrl ?: self::BASE_URL).'/'.ltrim($path, '/');
    }

    private function http(): GuzzleClient
    {
        return $this->http ??= ProviderHttpClient::guzzle();
    }
}
