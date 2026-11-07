<?php

namespace App\Support;

use App\Models\AiSetting;
use App\Models\Setting;

/**
 * Job Description Analyzer AI configuration (Filament → AI Settings →
 * "Job Description Analyzer AI").
 *
 * Stored as one key/value JSON row, exactly like AtsSettings and ToolSettings —
 * no columns, no migration. The prompt is not here: it lives in code
 * (App\Services\Jd\JdPrompt) and is only ever displayed. API keys stay on
 * AiSetting (the shared encrypted openai_api_key / gemini_api_key).
 */
class JdSettings
{
    public const SETTINGS_KEY = 'jd_settings';

    /** The AI stage key the analyzer tool exposes (one call). */
    public const STAGE = 'analyze';

    public static function provider(): string
    {
        $p = (string) (self::all()['provider'] ?? '');

        return self::isKnownProvider($p) ? $p : AiSetting::PROVIDER_OPENAI;
    }

    public static function openaiModel(): string
    {
        return self::string('openai_model') ?: 'gpt-4o-mini';
    }

    public static function geminiModel(): string
    {
        return self::string('gemini_model') ?: 'gemini-1.5-flash';
    }

    public static function modelFor(string $provider): string
    {
        return $provider === AiSetting::PROVIDER_GEMINI ? self::geminiModel() : self::openaiModel();
    }

    public static function temperature(): float
    {
        $t = self::all()['temperature'] ?? null;

        return $t === null ? 0.30 : max(0.0, min(2.0, (float) $t));
    }

    public static function maxTokens(): int
    {
        return max(256, (int) (self::all()['max_tokens'] ?? 1200));
    }

    /**
     * The effective provider/model/temperature/max-tokens for a run.
     *
     * Global values, with a per-tool override on the `analyze` stage winning for
     * provider and model. Call with no tool for the plain global config — that
     * is the entry a logged-in caller uses.
     *
     * @return array{provider: string, model: string, temperature: float, max_tokens: int}
     */
    public static function resolvedFor(?string $tool = null): array
    {
        $provider = self::provider();
        $model = self::modelFor($provider);

        if ($tool !== null) {
            $overrideProvider = ToolSettings::aiProvider($tool, self::STAGE);

            if (self::isKnownProvider($overrideProvider)) {
                $provider = $overrideProvider;
                $model = self::modelFor($provider);
            }

            $overrideModel = trim(ToolSettings::aiModel($tool, self::STAGE));

            if ($overrideModel !== '') {
                $model = $overrideModel;
            }
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'temperature' => self::temperature(),
            'max_tokens' => self::maxTokens(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Store */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $row = Setting::query()->where('key', self::SETTINGS_KEY)->first();

        return (array) ($row?->value ?? []);
    }

    public static function put(string $key, mixed $value): void
    {
        $values = self::all();
        $values[$key] = $value;

        Setting::query()->updateOrCreate(['key' => self::SETTINGS_KEY], ['value' => $values]);
    }

    private static function string(string $key): string
    {
        return trim((string) (self::all()[$key] ?? ''));
    }

    private static function isKnownProvider(string $provider): bool
    {
        return in_array($provider, [AiSetting::PROVIDER_OPENAI, AiSetting::PROVIDER_GEMINI], true);
    }
}
