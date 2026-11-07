<?php

namespace App\Services\Ai\Batch\Contracts;

use App\Models\AiSetting;
use App\Models\ProviderBatch;
use App\Services\Ai\Batch\BatchItem;
use App\Services\Ai\Batch\BatchResultItem;

/**
 * A provider's async Batch API. Implementations translate provider-agnostic
 * BatchItems into the provider's batch format, submit them, report status, and
 * return results mapped back by custom id.
 */
interface BatchScoringDriver
{
    public function name(): string;

    /**
     * Submit the items as one provider batch.
     *
     * @param  BatchItem[]  $items
     * @return array{provider_batch_id: string, input_file_id: ?string}
     */
    public function submit(array $items, AiSetting $settings): array;

    /**
     * Poll the provider for current status.
     *
     * @return array{status: string, output_file_id: ?string, completed: int, failed: int, error: ?string}
     */
    public function poll(ProviderBatch $batch, AiSetting $settings): array;

    /**
     * Fetch results once the batch has completed.
     *
     * @return BatchResultItem[]
     */
    public function results(ProviderBatch $batch, AiSetting $settings): array;
}
