<?php

namespace App\Jobs;

use App\Models\ApiJob;
use App\Models\Subscription;
use App\Models\UsageLog;
use App\Services\Ai\AiServiceManager;
use App\Services\Billing\CreditService;
use App\Services\Matching\MatchRubric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScoreMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 5;

    public function __construct(
        public readonly string $apiJobId,
        public readonly string $jobText,
        public readonly string $applicationText,
        public readonly array $requestPayload,
        public readonly ?string $ipAddress,
    ) {}

    public function handle(AiServiceManager $ai): void
    {
        $apiJob = ApiJob::query()->findOrFail($this->apiJobId);
        $apiJob->markProcessing();

        // Mirrors the synchronous endpoint, rubric scoring included.
        $result = MatchRubric::apply($ai->scoreDetailed($this->jobText, $this->applicationText));

        UsageLog::create([
            'user_id' => $apiJob->user_id,
            'subscription_id' => $apiJob->subscription_id,
            'endpoint' => $apiJob->endpoint,
            'lane' => UsageLog::LANE_ASYNC,
            'credits_consumed' => $apiJob->credits_charged,
            'ai_provider' => $result->provider,
            'ai_model' => $result->model,
            'tokens_used' => $result->tokensUsed,
            'request_payload' => $this->requestPayload,
            'response_payload' => $result->rawResponse,
            'score' => $result->score,
            'status' => UsageLog::STATUS_SUCCESS,
            'ip_address' => $this->ipAddress,
        ]);

        // Internal AI details (provider, model, tokens) stay in usage_logs only —
        // the job result is exposed verbatim through GET /jobs/{id}.
        $apiJob->markCompleted([
            'score' => $result->score,
            'reason' => $result->reason,
            'matched_skills' => $result->matchedSkills,
            'missing_skills' => $result->missingSkills,
            'credits_remaining' => $apiJob->subscription->fresh()->credits_remaining,
        ]);
    }

    /**
     * Called after final retry attempt fails. Refunds the exact amount charged.
     */
    public function failed(Throwable $e): void
    {
        $apiJob = ApiJob::query()->find($this->apiJobId);
        if (! $apiJob) {
            Log::error('ScoreMatchingJob failed but ApiJob not found', ['job_id' => $this->apiJobId]);

            return;
        }

        $charged = (int) $apiJob->credits_charged;
        $subscription = Subscription::query()->find($apiJob->subscription_id);

        if ($subscription && $charged > 0) {
            try {
                app(CreditService::class)->refund($subscription, $charged);
            } catch (Throwable $refundError) {
                Log::error('Refund failed inside ScoreMatchingJob::failed', [
                    'api_job_id' => $apiJob->id,
                    'charged' => $charged,
                    'error' => $refundError->getMessage(),
                ]);
            }
        }

        UsageLog::create([
            'user_id' => $apiJob->user_id,
            'subscription_id' => $apiJob->subscription_id,
            'endpoint' => $apiJob->endpoint,
            'lane' => UsageLog::LANE_ASYNC,
            'credits_consumed' => 0,
            'request_payload' => $this->requestPayload,
            'status' => UsageLog::STATUS_REFUNDED,
            'error_message' => $e->getMessage(),
            'ip_address' => $this->ipAddress,
        ]);

        $apiJob->markFailed($e, $charged);

        Log::warning('Async matching-score job failed; credits refunded', [
            'api_job_id' => $apiJob->id,
            'refunded' => $charged,
            'error' => $e->getMessage(),
        ]);
    }
}
