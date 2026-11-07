<?php

namespace App\Services\Ai\Batch;

/**
 * One provider-agnostic request in a batch: a system + user prompt for a single
 * CV, tagged with a custom id the driver echoes back so results can be mapped
 * to their resume file.
 */
final class BatchItem
{
    public function __construct(
        public readonly string $customId,
        public readonly string $systemPrompt,
        public readonly string $userMessage,
        public readonly string $model,
        public readonly float $temperature,
        public readonly int $maxTokens,
    ) {}
}
