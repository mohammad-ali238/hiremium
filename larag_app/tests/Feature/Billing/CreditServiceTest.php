<?php

namespace Tests\Feature\Billing;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscription(int $credits = 5): Subscription
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Test Plan',
            'credit_limit' => 100,
            'price' => 0,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'credits_remaining' => $credits,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_charge_decrements_credits(): void
    {
        $sub = $this->makeSubscription(5);
        $svc = new CreditService;

        $result = $svc->charge($sub, 1);

        $this->assertSame(4, $result->credits_remaining);
        $this->assertSame(4, $sub->fresh()->credits_remaining);
    }

    public function test_refund_increments_credits(): void
    {
        $sub = $this->makeSubscription(5);
        $svc = new CreditService;

        $svc->charge($sub, 1);
        $svc->refund($sub, 1);

        $this->assertSame(5, $sub->fresh()->credits_remaining);
    }

    public function test_charge_throws_when_insufficient(): void
    {
        $sub = $this->makeSubscription(0);
        $svc = new CreditService;

        $this->expectException(InsufficientCreditsException::class);

        $svc->charge($sub, 1);
    }

    public function test_charge_throws_when_subscription_expired(): void
    {
        $sub = $this->makeSubscription(10);
        $sub->update(['ends_at' => now()->subDay()]);

        $this->expectException(InsufficientCreditsException::class);

        (new CreditService)->charge($sub, 1);
    }

    public function test_charge_does_not_go_below_zero_under_repeated_calls(): void
    {
        $sub = $this->makeSubscription(3);
        $svc = new CreditService;

        $svc->charge($sub, 1);
        $svc->charge($sub, 1);
        $svc->charge($sub, 1);

        try {
            $svc->charge($sub, 1);
            $this->fail('Expected InsufficientCreditsException');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(0, $sub->fresh()->credits_remaining);
        }
    }
}
