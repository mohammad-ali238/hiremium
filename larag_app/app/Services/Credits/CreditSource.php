<?php

namespace App\Services\Credits;

/**
 * Abstraction over "a thing that holds AI credits and records usage" so the AI
 * endpoints can serve both paid subscriptions and free trials through one code
 * path. Implementations own their own ledger + usage-log table.
 */
interface CreditSource
{
    /**
     * Atomically deduct credits. Throws InsufficientCreditsException when the
     * balance would go below zero.
     */
    public function charge(int $credits): void;

    /**
     * Return credits previously charged (used when the AI call fails after charge).
     */
    public function refund(int $credits): void;

    /**
     * Credits currently remaining.
     */
    public function remaining(): int;

    /**
     * Assert the source can afford a cost, throwing a descriptive
     * InsufficientCreditsException otherwise. (Pre-check for variable-cost calls.)
     */
    public function ensureCanAfford(int $credits): void;

    /**
     * Record a successful call.
     *
     * @param  array<string,mixed>  $data  credits_consumed, ai_provider, ai_model,
     *                                     tokens_used, request_payload, response_payload,
     *                                     score, ip_address
     */
    public function logSuccess(string $endpoint, array $data): void;

    /**
     * Record a failed (refunded) call.
     *
     * @param  array<string,mixed>  $requestPayload
     */
    public function logFailure(string $endpoint, array $requestPayload, string $error, ?string $ip = null): void;

    /**
     * 'subscription' | 'trial' — for logging/telemetry.
     */
    public function type(): string;
}
