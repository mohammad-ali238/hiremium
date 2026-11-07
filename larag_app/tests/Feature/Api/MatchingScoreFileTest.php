<?php

namespace Tests\Feature\Api;

use App\Models\AiSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\AiServiceManager;
use App\Services\ResumeParsing\ResumeTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class MatchingScoreFileTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setupUser(int $credits = 5): array
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

    private function fakeResult(): AiScoreResult
    {
        return new AiScoreResult(
            score: 80, reason: 'Good match.', tokensUsed: 100,
            provider: 'openai', model: 'gpt-4o-mini', rawResponse: ['fake' => true],
        );
    }

    private function jobFields(): array
    {
        return [
            'job' => [
                'title' => 'Senior PHP Engineer',
                'description' => 'Build APIs in Laravel.',
            ],
        ];
    }

    public function test_cv_file_without_resume_text_is_accepted_and_reaches_ai_prompt(): void
    {
        [$user, $sub, $token] = $this->setupUser();

        $manager = Mockery::mock(AiServiceManager::class);
        $manager->shouldReceive('scoreDetailed')
            ->once()
            ->withArgs(function ($jobText, $applicationText) {
                return str_contains($applicationText, 'Resume file content:')
                    && str_contains($applicationText, 'UNIQUE_CV_MARKER_XYZ');
            })
            ->andReturn($this->fakeResult());
        $this->app->instance(AiServiceManager::class, $manager);

        $file = UploadedFile::fake()->createWithContent(
            'cv.txt',
            "Jane Doe — Senior Engineer.\nUNIQUE_CV_MARKER_XYZ\n".str_repeat('Laravel expert. ', 30)
        );

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/matching-score', $this->jobFields() + [
                'application' => ['name' => 'Jane Doe'],
                'resume_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('score', 80);
        $this->assertSame(4, $sub->fresh()->credits_remaining);

        // Usage log stores file meta + extracted text, never the UploadedFile object.
        $log = \App\Models\UsageLog::where('user_id', $user->id)->first();
        $this->assertSame('cv.txt', $log->request_payload['resume_file']['filename']);
        $this->assertStringContainsString('UNIQUE_CV_MARKER_XYZ', $log->request_payload['resume_file_text']);
    }

    public function test_cv_file_with_no_application_fields_at_all_is_accepted(): void
    {
        [, $sub, $token] = $this->setupUser();

        $manager = Mockery::mock(AiServiceManager::class);
        $manager->shouldReceive('scoreDetailed')->once()->andReturn($this->fakeResult());
        $this->app->instance(AiServiceManager::class, $manager);

        $file = UploadedFile::fake()->createWithContent(
            'cv.txt',
            str_repeat('Full stack developer with Laravel and Vue experience. ', 20)
        );

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/matching-score', $this->jobFields() + [
                'resume_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSame(4, $sub->fresh()->credits_remaining);
    }

    public function test_missing_both_resume_text_and_file_returns_422(): void
    {
        [, , $token] = $this->setupUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', $this->jobFields() + [
                'application' => ['name' => 'Jane Doe'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_oversized_file_returns_422(): void
    {
        [, $sub, $token] = $this->setupUser();

        // 4 MB > 3 MB cap
        $file = UploadedFile::fake()->create('cv.pdf', 4096, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/matching-score', $this->jobFields() + [
                'application' => ['resume_text' => 'text'],
                'resume_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame(5, $sub->fresh()->credits_remaining, 'no charge on validation failure');
    }

    public function test_unsupported_file_extension_returns_422(): void
    {
        [, , $token] = $this->setupUser();

        $file = UploadedFile::fake()->create('cv.exe', 100, 'application/octet-stream');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/matching-score', $this->jobFields() + [
                'application' => ['resume_text' => 'text'],
                'resume_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_async_endpoint_accepts_cv_file(): void
    {
        [, $sub, $token] = $this->setupUser();

        \Illuminate\Support\Facades\Queue::fake();

        $file = UploadedFile::fake()->createWithContent(
            'cv.txt',
            str_repeat('Senior Laravel developer with API experience. ', 20)
        );

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/matching-score/async', $this->jobFields() + [
                'application' => ['name' => 'Jane'],
                'resume_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(202);
        $response->assertJsonPath('status', 'queued');
        $this->assertSame(4, $sub->fresh()->credits_remaining);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ScoreMatchingJob::class, function ($job) {
            return str_contains($job->applicationText, 'Resume file content:');
        });
    }

    public function test_extractor_trims_to_3000_words(): void
    {
        $extractor = new ResumeTextExtractor();

        $text = implode(' ', array_fill(0, 4000, 'w'));
        $limited = $extractor->limit($text);

        $this->assertCount(3000, preg_split('/\s+/', $limited, -1, PREG_SPLIT_NO_EMPTY));
    }

    public function test_extractor_trims_to_10000_chars(): void
    {
        $extractor = new ResumeTextExtractor();

        // 2000 words of 9 chars each ≈ 20k chars — under word cap, over char cap.
        $text = implode(' ', array_fill(0, 2000, 'abcdefghi'));
        $limited = $extractor->limit($text);

        $this->assertSame(10000, mb_strlen($limited));
    }
}
