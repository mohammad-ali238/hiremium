<?php

namespace App\Services\Ai\Batch;

/**
 * One result line returned by a provider batch, mapped back by custom id.
 */
final class BatchResultItem
{
    public function __construct(
        public readonly string $customId,
        public readonly bool $ok,
        public readonly string $content = '',
        public readonly ?string $error = null,
    ) {}
}
