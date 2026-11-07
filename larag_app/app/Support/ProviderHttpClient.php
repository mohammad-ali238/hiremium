<?php

namespace App\Support;

use Gemini;
use Gemini\Client as GeminiClient;
use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use OpenAI\Client as OpenAiClient;

/**
 * Builds the OpenAI / Gemini SDK clients with a shared Guzzle HTTP client.
 *
 * When "Verify SSL certificates" is turned OFF in Site Settings, the Guzzle
 * client is created with `verify => false`, which lets outgoing HTTPS calls
 * succeed behind a self-signed / intercepting certificate (local dev only).
 * With the setting ON (the default) certificates are verified normally.
 */
class ProviderHttpClient
{
    public static function openai(string $apiKey): OpenAiClient
    {
        return OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(self::guzzle())
            ->make();
    }

    public static function gemini(string $apiKey): GeminiClient
    {
        return Gemini::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(self::guzzle())
            ->make();
    }

    /** Shared Guzzle client (respects the "Verify SSL certificates" setting). */
    public static function guzzle(): GuzzleClient
    {
        return new GuzzleClient([
            'verify' => SiteSettings::sslVerifyEnabled(),
        ]);
    }
}
