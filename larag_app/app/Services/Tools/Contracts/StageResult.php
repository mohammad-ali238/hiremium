<?php

namespace App\Services\Tools\Contracts;

/**
 * What a stage reports back about the work it just did — everything the run's
 * event log needs. A stage that made no AI call returns StageResult::local().
 */
final class StageResult
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly bool $providerInherited = true,
        public readonly ?int $tokensTotal = null,
        public readonly ?int $tokensPrompt = null,
        public readonly ?int $tokensCompletion = null,
        /** Request preview + raw response, stored only when the admin allows it. */
        public readonly array $payload = [],
        public readonly bool $isAiCall = false,
    ) {}

    /** A stage that ran locally: no provider, no tokens, nothing billed. */
    public static function local(array $payload = []): self
    {
        return new self(payload: $payload);
    }
}
