<?php

namespace App\Services\Ai\Contracts;

use App\Models\AiSetting;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\Exceptions\AiProviderException;

interface AiScoringProvider
{
    /**
     * Generate a matching score for a job + application pair.
     *
     * @throws AiProviderException
     */
    public function score(string $jobText, string $applicationText, AiSetting $settings): AiScoreResult;

    public function name(): string;
}
