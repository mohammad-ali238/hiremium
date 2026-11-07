<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ParseResumeRequest;
use App\Http\Resources\Api\ApiJobResource;
use App\Jobs\ParseResumeJob;
use App\Models\AiSetting;
use App\Models\ApiJob;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CreditService;
use App\Services\ResumeParsing\FileProcessor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AsyncParseResumeController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly FileProcessor $files,
    ) {}

    public function __invoke(ParseResumeRequest $request)
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Subscription $subscription */
        $subscription = $request->attributes->get('subscription');

        $settings = AiSetting::current();
        $upload = $request->file('file');

        // Process synchronously (fast, local) to determine the actual cost.
        $processed = $this->files->process($upload);

        $cost = $processed->isText()
            ? (int) $settings->parsing_credit_cost_text
            : (int) $settings->parsing_credit_cost_vision;

        if ($subscription->credits_remaining < $cost) {
            throw new InsufficientCreditsException(
                "This call requires {$cost} credits but only {$subscription->credits_remaining} remain."
            );
        }

        // Persist the file only when we need to send binary to the AI later.
        $storedPath = null;
        $payload = $processed->content;

        if ($processed->isImage()) {
            $ext = pathinfo($processed->filename, PATHINFO_EXTENSION) ?: 'bin';
            $storedPath = 'resume-uploads/'.Str::uuid()->toString().'.'.$ext;
            Storage::disk('local')->put($storedPath, file_get_contents($upload->getRealPath()));
            $payload = $storedPath; // job will resolve via Storage::disk('local')->path()
        }

        $subscription = $this->credits->charge($subscription, $cost);

        $apiJob = ApiJob::create([
            'user_id' => $user->getKey(),
            'subscription_id' => $subscription->getKey(),
            'endpoint' => 'resume-parse.v1',
            'status' => ApiJob::STATUS_QUEUED,
            'credits_charged' => $cost,
            'input_meta' => $processed->metaForLog() + ['mode' => 'async'],
            'queued_at' => now(),
        ]);

        ParseResumeJob::dispatch(
            apiJobId: $apiJob->id,
            type: $processed->type,
            content: $payload,
            mime: $processed->mime,
            filename: $processed->filename,
            size: $processed->size,
            requestMeta: $processed->metaForLog(),
            ipAddress: $request->ip(),
        );

        return (new ApiJobResource($apiJob))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
