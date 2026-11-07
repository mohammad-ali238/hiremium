<?php

namespace App\Services\Ai\Batch\Drivers;

use App\Models\AiSetting;
use App\Models\ProviderBatch;
use App\Services\Ai\Batch\BatchResultItem;
use App\Services\Ai\Batch\Contracts\BatchScoringDriver;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Support\ProviderHttpClient;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Throwable;

/**
 * OpenAI Batch API driver (SDK-native): writes a JSONL of /v1/chat/completions
 * requests, uploads it, creates a batch, polls it, and downloads the results.
 */
class OpenAiBatchDriver implements BatchScoringDriver
{
    public function __construct(private ?OpenAiClient $client = null) {}

    public function name(): string
    {
        return 'openai';
    }

    public function submit(array $items, AiSetting $settings): array
    {
        $client = $this->client($settings);

        // 1) Build the JSONL — one chat-completions request per CV.
        $lines = [];
        foreach ($items as $item) {
            $lines[] = json_encode([
                'custom_id' => $item->customId,
                'method' => 'POST',
                'url' => '/v1/chat/completions',
                'body' => [
                    'model' => $item->model,
                    'temperature' => $item->temperature,
                    'max_tokens' => $item->maxTokens,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $item->systemPrompt],
                        ['role' => 'user', 'content' => $item->userMessage],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $path = tempnam(sys_get_temp_dir(), 'oaib_').'.jsonl';
        file_put_contents($path, implode("\n", $lines));

        try {
            // 2) Upload the input file (purpose: batch).
            $upload = $client->files()->upload([
                'purpose' => 'batch',
                'file' => fopen($path, 'r'),
            ]);
            $inputFileId = $upload->id;

            // 3) Create the batch.
            $batch = $client->batches()->create([
                'input_file_id' => $inputFileId,
                'endpoint' => '/v1/chat/completions',
                'completion_window' => '24h',
            ])->toArray();
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI batch submit failed: '.$e->getMessage(), 0, $e);
        } finally {
            @unlink($path);
        }

        return [
            'provider_batch_id' => (string) $batch['id'],
            'input_file_id' => $inputFileId,
        ];
    }

    public function poll(ProviderBatch $batch, AiSetting $settings): array
    {
        try {
            $data = $this->client($settings)->batches()->retrieve($batch->provider_batch_id)->toArray();
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI batch poll failed: '.$e->getMessage(), 0, $e);
        }

        $counts = $data['request_counts'] ?? [];

        return [
            'status' => $this->mapStatus((string) ($data['status'] ?? '')),
            'output_file_id' => $data['output_file_id'] ?? null,
            'completed' => (int) ($counts['completed'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'error' => $data['errors']['data'][0]['message'] ?? null,
        ];
    }

    public function results(ProviderBatch $batch, AiSetting $settings): array
    {
        if (! $batch->output_file_id) {
            return [];
        }

        try {
            $jsonl = $this->client($settings)->files()->download($batch->output_file_id);
        } catch (Throwable $e) {
            throw new AiProviderException('OpenAI batch results download failed: '.$e->getMessage(), 0, $e);
        }

        $out = [];
        foreach (preg_split('/\r?\n/', trim((string) $jsonl)) as $line) {
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row) || ! isset($row['custom_id'])) {
                continue;
            }

            $status = (int) ($row['response']['status_code'] ?? 0);
            $content = $row['response']['body']['choices'][0]['message']['content'] ?? '';

            $out[] = new BatchResultItem(
                customId: (string) $row['custom_id'],
                ok: $status >= 200 && $status < 300 && $content !== '',
                content: (string) $content,
                error: $row['error']['message'] ?? ($status ? null : 'No response'),
            );
        }

        return $out;
    }

    /** Map OpenAI batch status → ProviderBatch status. */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'completed' => ProviderBatch::STATUS_COMPLETED,
            'failed', 'expired' => ProviderBatch::STATUS_FAILED,
            'cancelling', 'cancelled' => ProviderBatch::STATUS_CANCELLED,
            default => ProviderBatch::STATUS_PROCESSING, // validating, in_progress, finalizing
        };
    }

    private function client(AiSetting $settings): OpenAiClient
    {
        if ($this->client) {
            return $this->client;
        }

        if (empty($settings->openai_api_key)) {
            throw new AiProviderException('OpenAI API key is not configured.');
        }

        return $this->client = ProviderHttpClient::openai($settings->openai_api_key);
    }
}
