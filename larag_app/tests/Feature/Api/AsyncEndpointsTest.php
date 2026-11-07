<?php

namespace Tests\Feature\Api;

use App\Jobs\ParseResumeJob;
use App\Jobs\ScoreMatchingJob;
use App\Models\AiSetting;
use App\Models\ApiJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\AiServiceManager;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\ParsedResume;
use App\Services\ResumeParsing\ResumeParserManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AsyncEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setupUser(int $credits = 10): array
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Test', 'credit_limit' => 100, 'price' => 0,
            'duration_days' => 30, 'is_active' => true,
        ]);
        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'credits_remaining' => $credits,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        AiSetting::current();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $sub, $token];
    }

    private function matchPayload(): array
    {
        return [
            'job' => [
                'title' => 'Senior PHP Engineer',
                'description' => 'Build APIs in Laravel.',
                'skills' => ['PHP'],
            ],
            'application' => [
                'resume_text' => 'I am a senior Laravel developer with strong API experience.',
            ],
        ];
    }

    public function test_async_matching_score_dispatches_job_and_returns_202(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 5);
        Queue::fake();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score/async', $this->matchPayload());

        $response->assertStatus(202);
        $response->assertJsonPath('status', 'queued');
        $response->assertJsonStructure(['job_id', 'status', 'status_url', 'credits' => ['charged', 'refunded']]);

        $this->assertSame(4, $sub->fresh()->credits_remaining, 'credit charged at dispatch');
        Queue::assertPushed(ScoreMatchingJob::class);
    }

    public function test_async_parse_resume_charges_text_cost_and_dispatches_job(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 10);
        Queue::fake();

        $file = UploadedFile::fake()->createWithContent(
            'cv.txt',
            str_repeat('Jane Doe — Senior Engineer at ACME. ', 20)
        );

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume/async', ['file' => $file]);

        $response->assertStatus(202);
        $response->assertJsonPath('credits.charged', 2);
        $this->assertSame(8, $sub->fresh()->credits_remaining);
        Queue::assertPushed(ParseResumeJob::class);
    }

    public function test_async_parse_resume_charges_vision_cost_for_image(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 10);
        Queue::fake();

        $file = UploadedFile::fake()->image('cv.png', 800, 1000);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume/async', ['file' => $file]);

        $response->assertStatus(202);
        $response->assertJsonPath('credits.charged', 5);
        $this->assertSame(5, $sub->fresh()->credits_remaining);
        Queue::assertPushed(ParseResumeJob::class);
    }

    public function test_async_parse_resume_blocks_insufficient_credits_before_charge(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 3);
        Queue::fake();

        $file = UploadedFile::fake()->image('cv.png');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume/async', ['file' => $file]);

        $response->assertStatus(402);
        $response->assertJsonPath('error.code', 'insufficient_credits');
        $this->assertSame(3, $sub->fresh()->credits_remaining);
        Queue::assertNotPushed(ParseResumeJob::class);
    }

    public function test_job_status_endpoint_returns_queued_then_completed(): void
    {
        [$user, , $token] = $this->setupUser(credits: 5);

        // Bind a stub AI that returns a deterministic score.
        $manager = Mockery::mock(AiServiceManager::class);
        $manager->shouldReceive('scoreDetailed')->andReturn(new AiScoreResult(
            score: 91, reason: 'Strong match.', tokensUsed: 100,
            provider: 'openai', model: 'gpt-4o-mini', rawResponse: ['fake' => true],
        ));
        $this->app->instance(AiServiceManager::class, $manager);

        // Sync driver runs jobs in-process.
        config(['queue.default' => 'sync']);

        $dispatchResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score/async', $this->matchPayload());

        $dispatchResp->assertStatus(202);
        $jobId = $dispatchResp->json('job_id');
        $this->assertNotEmpty($jobId);

        // With sync driver, the job already ran. Status should be completed.
        $statusResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/jobs/' . $jobId);

        $statusResp->assertOk();
        $statusResp->assertJsonPath('status', 'completed');
        $statusResp->assertJsonPath('result.score', 91);
        $statusResp->assertJsonPath('result.reason', 'Strong match.');
    }

    public function test_job_status_404_for_unknown_uuid(): void
    {
        [, , $token] = $this->setupUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/jobs/' . \Illuminate\Support\Str::uuid());

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'job_not_found');
    }

    public function test_other_users_cannot_view_someone_elses_job(): void
    {
        [, , $token]      = $this->setupUser(credits: 5);
        [$otherUser, , ] = $this->setupUser(credits: 5);

        // Create a job owned by otherUser
        $otherJob = ApiJob::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $otherUser->id,
            'subscription_id' => $otherUser->subscriptions()->first()->id,
            'endpoint' => 'matching-score.v1',
            'status' => ApiJob::STATUS_QUEUED,
            'credits_charged' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/jobs/' . $otherJob->id);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'job_access_denied');
    }

    /**
     * Dispatch via Queue::fake(), then simulate the worker by invoking handle()
     * and routing the resulting exception to failed() — exactly what Laravel's
     * queue worker does after the final retry attempt.
     */
    private function runQueuedJobExpectingFailure(string $jobClass, string $endpoint, $payload, string $token)
    {
        Queue::fake();

        if ($payload instanceof UploadedFile) {
            $dispatchResp = $this->withHeader('Authorization', "Bearer {$token}")
                ->post($endpoint, ['file' => $payload]);
        } else {
            $dispatchResp = $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson($endpoint, $payload);
        }

        $dispatchResp->assertStatus(202);

        $captured = null;
        Queue::assertPushed($jobClass, function ($job) use (&$captured) {
            $captured = $job;
            return true;
        });
        $this->assertNotNull($captured, 'No job was pushed to the queue.');

        try {
            $this->app->call([$captured, 'handle']);
            $this->fail('Expected job to throw, but it did not.');
        } catch (\Throwable $e) {
            $captured->failed($e);
        }

        return $dispatchResp;
    }

    public function test_failed_async_matching_score_refunds_exact_charged_amount(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 5);

        $manager = Mockery::mock(AiServiceManager::class);
        $manager->shouldReceive('scoreDetailed')
            ->andThrow(new AiProviderException('upstream down'));
        $this->app->instance(AiServiceManager::class, $manager);

        $dispatchResp = $this->runQueuedJobExpectingFailure(
            ScoreMatchingJob::class,
            '/api/v1/matching-score/async',
            $this->matchPayload(),
            $token,
        );

        $this->assertSame(5, $sub->fresh()->credits_remaining, 'credit refunded on failure');

        $apiJob = ApiJob::find($dispatchResp->json('job_id'));
        $this->assertSame('failed', $apiJob->status);
        $this->assertSame(1, $apiJob->credits_refunded);
    }

    public function test_failed_async_parse_resume_refunds_vision_amount_when_image_upload(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 10);

        $manager = Mockery::mock(ResumeParserManager::class);
        $manager->shouldReceive('parse')
            ->andThrow(new AiProviderException('upstream down'));
        $this->app->instance(ResumeParserManager::class, $manager);

        $file = UploadedFile::fake()->image('cv.png', 800, 1000);

        $dispatchResp = $this->runQueuedJobExpectingFailure(
            ParseResumeJob::class,
            '/api/v1/parse-resume/async',
            $file,
            $token,
        );

        $dispatchResp->assertJsonPath('credits.charged', 5);
        $this->assertSame(10, $sub->fresh()->credits_remaining, 'vision cost (5) fully refunded');

        $apiJob = ApiJob::find($dispatchResp->json('job_id'));
        $this->assertSame('failed', $apiJob->status);
        $this->assertSame(5, $apiJob->credits_refunded);
    }

    public function test_failed_async_parse_resume_refunds_text_amount_when_text_upload(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 10);

        $manager = Mockery::mock(ResumeParserManager::class);
        $manager->shouldReceive('parse')
            ->andThrow(new AiProviderException('upstream down'));
        $this->app->instance(ResumeParserManager::class, $manager);

        $file = UploadedFile::fake()->createWithContent(
            'cv.txt',
            str_repeat('Jane Doe — Senior Engineer at ACME. ', 20)
        );

        $dispatchResp = $this->runQueuedJobExpectingFailure(
            ParseResumeJob::class,
            '/api/v1/parse-resume/async',
            $file,
            $token,
        );

        $dispatchResp->assertJsonPath('credits.charged', 2);
        $this->assertSame(10, $sub->fresh()->credits_remaining, 'text cost (2) fully refunded');

        $apiJob = ApiJob::find($dispatchResp->json('job_id'));
        $this->assertSame(2, $apiJob->credits_refunded);
    }
}
