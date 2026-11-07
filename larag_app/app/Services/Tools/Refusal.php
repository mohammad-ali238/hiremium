<?php

namespace App\Services\Tools;

/**
 * A gate saying no. `code` is what the admin sees in the logs (precise);
 * `message` is what the visitor reads (deliberately general — a refusal must
 * never teach someone which signal caught them or how to get around it).
 */
class Refusal
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        /** Form field to attach the error to, or null for the page-level banner. */
        public readonly ?string $field = null,
    ) {}
}
