<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MatchingScoreRequest;
use App\Http\Resources\Api\MatchingScoreResource;
use App\Models\AiSetting;
use App\Services\Ai\AiServiceManager;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Credits\CreditSource;
use App\Services\Matching\MatchRubric;
use App\Services\ResumeParsing\ResumeTextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MatchingScoreController extends Controller
{
    public function __construct(
        private readonly AiServiceManager $ai,
        private readonly ResumeTextExtractor $extractor,
    ) {}

    public function __invoke(MatchingScoreRequest $request): JsonResponse|MatchingScoreResource
    {
        /** @var CreditSource $source */
        $source = $request->attributes->get('credit_source');

        $endpoint = 'matching-score.v1';

        // 1. Extract CV file text if provided (throws before any charge on bad files)
        $resumeFileText = $request->hasFile('resume_file')
            ? $this->extractor->extract($request->file('resume_file'))
            : null;

        $logPayload = $request->logPayload($resumeFileText);

        // 2. Resolve cost (trials use their own configurable cost; paid = 1) and charge.
        $cost = $source->type() === 'trial'
            ? max(1, (int) AiSetting::current()->trial_credit_cost_match)
            : 1;

        $source->ensureCanAfford($cost);
        $source->charge($cost);

        // 3. Call AI provider; refund on failure
        try {
            // The model reports findings; MatchRubric computes the score from
            // them with fixed weights. The caller sends no nice-to-have list, so
            // the 50/25/15/10 split applies.
            $result = MatchRubric::apply(
                $this->ai->scoreDetailed($request->jobText(), $request->applicationText($resumeFileText))
            );
        } catch (AiProviderException $e) {
            $source->refund($cost);
            $source->logFailure($endpoint, $logPayload, $e->getMessage(), $request->ip());

            Log::warning('AI scoring failed; credit refunded', [
                'source' => $source->type(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // 4. Log success
        $source->logSuccess($endpoint, [
            'credits_consumed' => $cost,
            'ai_provider' => $result->provider,
            'ai_model' => $result->model,
            'tokens_used' => $result->tokensUsed,
            'request_payload' => $logPayload,
            'response_payload' => $result->rawResponse,
            'score' => $result->score,
            'ip_address' => $request->ip(),
        ]);

        return new MatchingScoreResource($result, $source->remaining());
    }
}
