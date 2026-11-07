<?php

namespace App\Jobs;

use App\Models\ApiJob;
use App\Models\Subscription;
use App\Models\UsageLog;
use App\Services\Billing\CreditService;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\ResumeParsing\ProcessedFile;
use App\Services\ResumeParsing\ResumeParserManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public int $backoff = 5;

    /**
     * @param  string  $apiJobId  ApiJob UUID.
     * @param  string  $type  ProcessedFile::TYPE_TEXT | TYPE_IMAGE.
     * @param  string  $content  Extracted text (when type=text) OR relative storage path under disk('local') (when type=image).
     * @param  array  $requestMeta  Safe meta for usage log (filename, mime, size, extraction_path).
     */
    public function __construct(
        public readonly string $apiJobId,
        public readonly string $type,
        public readonly string $content,
        public readonly string $mime,
        public readonly string $filename,
        public readonly int $size,
        public readonly array $requestMeta,
        public readonly ?string $ipAddress,
    ) {}

    public function handle(ResumeParserManager $parser): void
    {
        $apiJob = ApiJob::query()->findOrFail($this->apiJobId);
        $apiJob->markProcessing();

        $processed = $this->buildProcessedFile();

        try {
            $result = $parser->parse($processed);
        } finally {
            $this->cleanupStoredFile();
        }

        UsageLog::create([
            'user_id' => $apiJob->user_id,
            'subscription_id' => $apiJob->subscription_id,
            'endpoint' => $apiJob->endpoint,
            'lane' => UsageLog::LANE_ASYNC,
            'credits_consumed' => $apiJob->credits_charged,
            'ai_provider' => $result->provider,
            'ai_model' => $result->model,
            'tokens_used' => $result->tokensUsed,
            'request_payload' => $this->requestMeta,
            'response_payload' => $result->rawResponse,
            'status' => UsageLog::STATUS_SUCCESS,
            'ip_address' => $this->ipAddress,
        ]);

        // Internal AI details (provider, model, tokens) stay in usage_logs only —
        // the job result is exposed verbatim through GET /jobs/{id}.
        $apiJob->markCompleted([
            'data' => $result->data,
            'meta' => [
                'extraction_path' => $this->type,
                'confidence_percent' => ParseConfidence::percent($result->confidence()),
                'credits_consumed' => $apiJob->credits_charged,
                'credits_remaining' => $apiJob->subscription->fresh()->credits_remaining,
            ],
        ]);
    }

    /**
     * Final-attempt failure handler. Refunds the EXACT amount charged at dispatch.
     */
    public function failed(Throwable $e): void
    {
        $this->cleanupStoredFile();

        $apiJob = ApiJob::query()->find($this->apiJobId);
        if (! $apiJob) {
            Log::error('ParseResumeJob failed but ApiJob not found', ['job_id' => $this->apiJobId]);

            return;
        }

        $charged = (int) $apiJob->credits_charged;
        $subscription = Subscription::query()->find($apiJob->subscription_id);

        if ($subscription && $charged > 0) {
            try {
                app(CreditService::class)->refund($subscription, $charged);
            } catch (Throwable $refundError) {
                Log::error('Refund failed inside ParseResumeJob::failed', [
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
            'request_payload' => $this->requestMeta,
            'status' => UsageLog::STATUS_REFUNDED,
            'error_message' => $e->getMessage(),
            'ip_address' => $this->ipAddress,
        ]);

        $apiJob->markFailed($e, $charged);

        Log::warning('Async parse-resume job failed; credits refunded', [
            'api_job_id' => $apiJob->id,
            'refunded' => $charged,
            'extraction' => $this->type,
            'error' => $e->getMessage(),
        ]);
    }

    private function buildProcessedFile(): ProcessedFile
    {
        $content = $this->content;

        if ($this->type === ProcessedFile::TYPE_IMAGE) {
            // For image/binary path, content holds a relative storage path; resolve to absolute.
            $content = Storage::disk('local')->path($this->content);
        }

        return new ProcessedFile(
            type: $this->type,
            content: $content,
            mime: $this->mime,
            filename: $this->filename,
            size: $this->size,
        );
    }

    private function cleanupStoredFile(): void
    {
        if ($this->type !== ProcessedFile::TYPE_IMAGE) {
            return;
        }

        if (Storage::disk('local')->exists($this->content)) {
            Storage::disk('local')->delete($this->content);
        }
    }
}
