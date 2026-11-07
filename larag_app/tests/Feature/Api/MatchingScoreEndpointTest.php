<?php

namespace Tests\Feature\Api;

use App\Models\AiSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\Ai\AiScoreResult;
use App\Services\Ai\AiServiceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MatchingScoreEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function payload(): array
    {
        return [
            'job' => [
                'title' => 'Senior PHP Engineer',
                'description' => 'Build APIs in Laravel.',
                'requirements' => '5+ years PHP, Laravel.',
                'skills' => ['PHP', 'Laravel'],
            ],
            'application' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'resume_text' => '8 years building Laravel APIs at scale.',
                'experience_years' => 8,
                'skills' => ['PHP', 'Laravel', 'Redis'],
            ],
        ];
    }

    private function setupUserWithPlan(int $credits = 5): array
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

    public function test_missing_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/matching-score', $this->payload());

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_no_subscription_returns_402(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', $this->payload());

        $response->assertStatus(402);
        $response->assertJsonPath('error.code', 'no_active_subscription');
    }

    public function test_zero_credits_returns_402(): void
    {
        [, , $token] = $this->setupUserWithPlan(credits: 0);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', $this->payload());

        $response->assertStatus(402);
        $response->assertJsonPath('error.code', 'insufficient_credits');
    }

    public function test_invalid_payload_returns_422(): void
    {
        [, , $token] = $this->setupUserWithPlan();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', ['job' => [], 'application' => []]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_successful_call_deducts_credit_and_logs_usage(): void
    {
        [$user, $sub, $token] = $this->setupUserWithPlan(credits: 5);

        $manager = Mockery::mock(AiServiceManager::class);
        $manager->shouldReceive('scoreDetailed')
            ->once()
            ->andReturn(new AiScoreResult(
                score: 87,
                reason: 'Strong match.',
                tokensUsed: 123,
                provider: 'openai',
                model: 'gpt-4o-mini',
                rawResponse: ['fake' => true],
            ));
        $this->app->instance(AiServiceManager::class, $manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', $this->payload());

        $response->assertOk();
        $response->assertJsonPath('score', 87);
        $response->assertJsonPath('credits_remaining', 4);

        // Internal AI details are logged but never exposed in the response.
        $response->assertJsonMissingPath('tokens_used');
        $response->assertJsonMissingPath('provider');
        $response->assertJsonMissingPath('model');

        $this->assertSame(4, $sub->fresh()->credits_remaining);
        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'status' => 'success',
            'score' => 87,
            'tokens_used' => 123,
        ]);
    }

    public function test_ai_failure_refunds_credit_and_logs_failure(): void
    {
        [$user, $sub, $token] = $this->setupUserWithPlan(credits: 5);

        $manager = Mockery::mock(AiServiceManager::class);
        $manager->shouldReceive('scoreDetailed')
            ->once()
            ->andThrow(new \App\Services\Ai\Exceptions\AiProviderException('upstream down'));
        $this->app->instance(AiServiceManager::class, $manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', $this->payload());

        $response->assertStatus(502);
        $response->assertJsonPath('error.code', 'ai_provider_error');

        $this->assertSame(5, $sub->fresh()->credits_remaining, 'credit should be refunded on AI failure');
        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'status' => 'refunded',
        ]);
    }
}
