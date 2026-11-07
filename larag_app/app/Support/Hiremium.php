<?php

namespace App\Support;

use App\Models\AiSetting;

/**
 * Small runtime helpers over Hiremium settings/config. Keeps env/config vs
 * admin-DB precedence in one place.
 */
class Hiremium
{
    /** Whether account-level BYO provider keys are allowed (admin DB wins). */
    public static function byoKeysEnabled(): bool
    {
        return (bool) (AiSetting::current()->byo_keys_enabled
            ?? config('hiremium.ai.byo_keys_enabled', false));
    }

    /** Credits charged per candidate match. */
    public static function creditCostPerMatch(): int
    {
        return (int) config('hiremium.ai.credit_cost_per_match', 1);
    }

    /**
     * CVs up to this count are processed on the immediate "live" lane; anything
     * beyond it in the same upload spills onto the deferred "batch" lane.
     */
    public static function batchThreshold(): int
    {
        return max(1, (int) config('hiremium.ai.batch_threshold', 100));
    }

    /**
     * How many overflow (batch-lane) CVs become due for processing per minute.
     * Staggers a large import so the live lane finishes first and the provider
     * API isn't hit in one burst.
     */
    public static function batchLanePerMinute(): int
    {
        return max(1, (int) config('hiremium.ai.batch_lane_per_minute', 60));
    }
}
