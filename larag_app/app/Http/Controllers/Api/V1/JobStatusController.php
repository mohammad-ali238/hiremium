<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\JobAccessDeniedException;
use App\Exceptions\Api\JobNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiJobResource;
use App\Models\ApiJob;
use Illuminate\Http\Request;

class JobStatusController extends Controller
{
    public function __invoke(Request $request, string $job): ApiJobResource
    {
        $apiJob = ApiJob::query()->find($job);

        if (! $apiJob) {
            throw new JobNotFoundException;
        }

        if ($apiJob->user_id !== $request->user()->getKey()) {
            throw new JobAccessDeniedException;
        }

        return new ApiJobResource($apiJob);
    }
}
