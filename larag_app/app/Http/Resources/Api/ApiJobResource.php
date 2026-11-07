<?php

namespace App\Http\Resources\Api;

use App\Models\ApiJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ApiJob $job */
        $job = $this->resource;

        return [
            'job_id' => $job->id,
            'endpoint' => $job->endpoint,
            'status' => $job->status,
            'queued_at' => optional($job->queued_at)->toIso8601String(),
            'started_at' => optional($job->started_at)->toIso8601String(),
            'finished_at' => optional($job->finished_at)->toIso8601String(),
            'result' => $job->result,
            'error' => $job->error_code ? [
                'code' => $job->error_code,
                'message' => $job->error_message,
            ] : null,
            'credits' => [
                'charged' => $job->credits_charged,
                'refunded' => $job->credits_refunded,
            ],
            'status_url' => route('api.v1.jobs.show', ['job' => $job->id]),
        ];
    }
}
