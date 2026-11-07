<?php

namespace App\Services\Tools;

use App\Models\AiSetting;
use App\Support\AtsSettings;
use App\Support\JdSettings;
use App\Support\ToolSettings;

/**
 * Which provider + model a tool's AI stage actually runs on.
 *
 * Free Tools settings may pin a provider and model PER TOOL, PER STAGE (tool #1
 * has two: `parse` and `match`). Anything left empty inherits the global AI
 * Settings, so one place still controls everything by default.
 *
 * Resolution never touches the stored AiSetting row: it hands the provider a
 * REPLICA with only the provider/model fields rewritten, so API keys, prompts,
 * temperature, token caps and the scoring gates all keep coming from AI
 * Settings exactly as they do for the recruiter pipeline.
 */
class ToolAi
{
    /** Stage key => the AiSetting fields that stage reads. */
    private const STAGE_FIELDS = [
        // Resume parsing: provider has a model column per provider.
        'parse' => [
            'provider' => 'parsing_ai_provider',
            'models' => [
                AiSetting::PROVIDER_OPENAI => 'parsing_openai_model',
                AiSetting::PROVIDER_GEMINI => 'parsing_gemini_model',
            ],
        ],
        // Match scoring: one shared model column.
        'match' => [
            'provider' => 'default_provider',
            'models' => [
                AiSetting::PROVIDER_OPENAI => 'default_model',
                AiSetting::PROVIDER_GEMINI => 'default_model',
            ],
        ],
    ];

    /**
     * The effective provider/model for a tool stage.
     *
     * @return array{provider: string, model: string, inherited: bool}
     */
    public static function resolve(string $tool, string $stage, ?AiSetting $settings = null): array
    {
        $settings ??= AiSetting::current();
        $inherited = self::inherited($stage, $settings);

        $provider = ToolSettings::aiProvider($tool, $stage);
        $model = ToolSettings::aiModel($tool, $stage);

        if (! self::isKnownProvider($provider)) {
            $provider = '';
        }

        // A model without a provider still applies — to the inherited provider.
        $effectiveProvider = $provider !== '' ? $provider : $inherited['provider'];

        // Switching provider without naming a model must not carry the other
        // provider's model name across; fall back to that provider's default.
        if ($model === '') {
            $model = $provider !== '' && $provider !== $inherited['provider']
                ? self::defaultModelFor($stage, $effectiveProvider, $settings)
                : $inherited['model'];
        }

        return [
            'provider' => $effectiveProvider,
            'model' => $model,
            'inherited' => $provider === '' && ToolSettings::aiModel($tool, $stage) === '',
        ];
    }

    /**
     * AI Settings with this tool/stage's provider + model applied — what the
     * parsing / scoring providers get handed.
     */
    public static function settingsFor(string $tool, string $stage, ?AiSetting $settings = null): AiSetting
    {
        $settings ??= AiSetting::current();
        $resolved = self::resolve($tool, $stage, $settings);

        // replicate() drops the primary key, so this copy can never be saved
        // over the real settings row by accident.
        $clone = $settings->replicate();
        $fields = self::STAGE_FIELDS[$stage] ?? null;

        if ($fields === null) {
            return $clone;
        }

        $clone->{$fields['provider']} = $resolved['provider'];

        $modelField = $fields['models'][$resolved['provider']] ?? null;
        if ($modelField !== null && $resolved['model'] !== '') {
            $clone->{$modelField} = $resolved['model'];
        }

        return $clone;
    }

    /**
     * What the stage would use with no override — powers the "inherits: …"
     * placeholder in the settings UI.
     *
     * @return array{provider: string, model: string}
     */
    public static function inherited(string $stage, ?AiSetting $settings = null): array
    {
        $settings ??= AiSetting::current();

        // The ATS tool's `score` stage is configured in AtsSettings (its own
        // JSON row), not on the AiSetting columns — so its inherited defaults,
        // used for the "Inherit: …" placeholder in Tools settings, come from
        // there rather than from the match defaults.
        if ($stage === AtsSettings::STAGE) {
            $provider = AtsSettings::provider();

            return ['provider' => $provider, 'model' => AtsSettings::modelFor($provider)];
        }

        if ($stage === JdSettings::STAGE) {
            $provider = JdSettings::provider();

            return ['provider' => $provider, 'model' => JdSettings::modelFor($provider)];
        }

        $fields = self::STAGE_FIELDS[$stage] ?? null;

        if ($fields === null) {
            return ['provider' => (string) $settings->default_provider, 'model' => (string) $settings->default_model];
        }

        $provider = (string) $settings->{$fields['provider']};

        return [
            'provider' => $provider,
            'model' => self::defaultModelFor($stage, $provider, $settings),
        ];
    }

    /** Provider options for the settings selects. @return array<string, string> */
    public static function providerOptions(): array
    {
        return [
            AiSetting::PROVIDER_OPENAI => 'OpenAI',
            AiSetting::PROVIDER_GEMINI => 'Google Gemini',
        ];
    }

    private static function defaultModelFor(string $stage, string $provider, AiSetting $settings): string
    {
        $field = self::STAGE_FIELDS[$stage]['models'][$provider] ?? null;

        return $field === null ? '' : (string) $settings->{$field};
    }

    private static function isKnownProvider(string $provider): bool
    {
        return array_key_exists($provider, self::providerOptions());
    }
}
