<?php

namespace Tests\Feature\Api;

use App\Models\AiSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\ParsedResume;
use App\Services\ResumeParsing\ResumeParserManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ParseResumeEndpointTest extends TestCase
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

    private function fakeResume(): ParsedResume
    {
        return new ParsedResume(
            data: ParsedResume::normalize([
                'fullName' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '+1 555 0100',
                'jobTitle' => 'Senior Engineer',
                'summary' => 'Veteran Laravel dev.',
                'experience' => [
                    ['company' => 'ACME', 'title' => 'Senior Engineer', 'location' => 'Berlin', 'startDate' => '2019-03', 'endDate' => 'Present', 'description' => 'APIs'],
                ],
                'education' => [
                    ['institution' => 'MIT', 'degree' => 'BSc', 'field' => 'CS', 'location' => 'Boston', 'startDate' => '2012-09', 'endDate' => '2016-06', 'grade' => '3.8 GPA'],
                ],
                'projects' => [],
                'certificates' => [],
                'awards' => [],
                'expertise' => [
                    ['name' => 'Backend Development', 'level' => 5],
                ],
                'languages' => [
                    ['name' => 'English', 'level' => 5],
                ],
                'skills' => ['PHP', 'Laravel'],
            ]),
            tokensUsed: 250,
            provider: 'openai',
            model: 'gpt-4o-mini',
            rawResponse: ['fake' => true],
        );
    }

    public function test_text_extraction_charges_text_cost(): void
    {
        [$user, $sub, $token] = $this->setupUser(credits: 10);

        $manager = Mockery::mock(ResumeParserManager::class);
        $manager->shouldReceive('parse')->once()->andReturn($this->fakeResume());
        $this->app->instance(ResumeParserManager::class, $manager);

        $file = UploadedFile::fake()->createWithContent(
            'cv.txt',
            str_repeat('Jane Doe — Senior PHP Engineer at ACME Corp. ', 20)
        );

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('data.fullName', 'Jane Doe');
        $response->assertJsonPath('meta.extraction_path', 'text');
        $response->assertJsonPath('meta.credits_consumed', 2);
        $response->assertJsonPath('meta.credits_remaining', 8);

        $this->assertSame(8, $sub->fresh()->credits_remaining);
        $this->assertDatabaseHas('usage_logs', [
            'user_id'  => $user->id,
            'endpoint' => 'resume-parse.v1',
            'status'   => 'success',
            'credits_consumed' => 2,
        ]);
    }

    public function test_image_upload_charges_vision_cost(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 10);

        $manager = Mockery::mock(ResumeParserManager::class);
        $manager->shouldReceive('parse')->once()->andReturn($this->fakeResume());
        $this->app->instance(ResumeParserManager::class, $manager);

        $file = UploadedFile::fake()->image('cv.png', 800, 1000);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('meta.extraction_path', 'image');
        $response->assertJsonPath('meta.credits_consumed', 5);
        $response->assertJsonPath('meta.credits_remaining', 5);

        $this->assertSame(5, $sub->fresh()->credits_remaining);
    }

    public function test_insufficient_credits_for_endpoint_returns_402(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 3);

        $file = UploadedFile::fake()->image('cv.png');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume', ['file' => $file]);

        $response->assertStatus(402);
        $response->assertJsonPath('error.code', 'insufficient_credits');
        $this->assertSame(3, $sub->fresh()->credits_remaining, 'no charge on rejection');
    }

    public function test_unsupported_file_type_returns_422(): void
    {
        [, $sub, $token] = $this->setupUser(credits: 10);

        $file = UploadedFile::fake()->create('virus.exe', 200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame(10, $sub->fresh()->credits_remaining);
    }

    public function test_ai_failure_refunds_credits(): void
    {
        [$user, $sub, $token] = $this->setupUser(credits: 10);

        $manager = Mockery::mock(ResumeParserManager::class);
        $manager->shouldReceive('parse')->once()
            ->andThrow(new AiProviderException('upstream down'));
        $this->app->instance(ResumeParserManager::class, $manager);

        $file = UploadedFile::fake()->createWithContent('cv.txt', str_repeat('x', 500));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume', ['file' => $file]);

        $response->assertStatus(502);
        $this->assertSame(10, $sub->fresh()->credits_remaining, 'credits should be refunded');
        $this->assertDatabaseHas('usage_logs', [
            'user_id'  => $user->id,
            'endpoint' => 'resume-parse.v1',
            'status'   => 'refunded',
        ]);
    }

    public function test_request_payload_log_does_not_contain_resume_content(): void
    {
        [$user, , $token] = $this->setupUser(credits: 10);

        $manager = Mockery::mock(ResumeParserManager::class);
        $manager->shouldReceive('parse')->once()->andReturn($this->fakeResume());
        $this->app->instance(ResumeParserManager::class, $manager);

        $secretText = 'TOP_SECRET_RESUME_BODY_'.str_repeat('y', 500);
        $file = UploadedFile::fake()->createWithContent('cv.txt', $secretText);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/parse-resume', ['file' => $file])
            ->assertOk();

        $log = \App\Models\UsageLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('TOP_SECRET_RESUME_BODY_', json_encode($log->request_payload));
        $this->assertSame(['filename', 'mime', 'size', 'extraction_path'], array_keys($log->request_payload));
    }
}
