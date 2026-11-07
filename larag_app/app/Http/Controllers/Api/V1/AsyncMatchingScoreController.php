<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MatchingScoreRequest;
use App\Http\Resources\Api\ApiJobResource;
use App\Jobs\ScoreMatchingJob;
use App\Models\ApiJob;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CreditService;
use App\Services\ResumeParsing\ResumeTextExtractor;
use Illuminate\Http\Response;

class AsyncMatchingScoreController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly ResumeTextExtractor $extractor,
    ) {}

    public function __invoke(MatchingScoreRequest $request)
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Subscription $subscription */
        $subscription = $request->attributes->get('subscription');

        // Extract CV file text up front (cheap, local) so the queued job only
        // carries strings — no file persistence needed. Throws before any charge.
        $resumeFileText = $request->hasFile('resume_file')
            ? $this->extractor->extract($request->file('resume_file'))
            : null;

        $cost = 1; // matching-score endpoint cost
        $subscription = $this->credits->charge($subscription, $cost);

        $apiJob = ApiJob::create([
            'user_id' => $user->getKey(),
            'subscription_id' => $subscription->getKey(),
            'endpoint' => 'matching-score.v1',
            'status' => ApiJob::STATUS_QUEUED,
            'credits_charged' => $cost,
            'input_meta' => ['mode' => 'async'],
            'queued_at' => now(),
        ]);

        ScoreMatchingJob::dispatch(
            apiJobId: $apiJob->id,
            jobText: $request->jobText(),
            applicationText: $request->applicationText($resumeFileText),
            requestPayload: $request->logPayload($resumeFileText),
            ipAddress: $request->ip(),
        );

        return (new ApiJobResource($apiJob))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
