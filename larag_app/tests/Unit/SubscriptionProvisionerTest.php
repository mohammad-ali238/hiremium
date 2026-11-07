<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, int $credits, int $days = 30): Plan
    {
        return Plan::create([
            'name' => $name, 'credit_limit' => $credits, 'price' => 10,
            'duration_days' => $days, 'is_active' => true,
        ]);
    }

    public function test_provision_supersedes_prior_active_and_sets_credits(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);

        $old = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id,
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25),
            'credits_remaining' => 3, 'status' => Subscription::STATUS_ACTIVE,
        ]);

        $sub = app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'lemonsqueezy_subscription_id' => 'ls_1',
            'renews_at' => now()->addDays(30),
        ]);

        $this->assertSame(Subscription::STATUS_SUPERSEDED, $old->refresh()->status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame(100, $sub->credits_remaining);
        $this->assertSame('ls_1', $sub->lemonsqueezy_subscription_id);
        $this->assertTrue($sub->ends_at->isFuture());
    }

    public function test_sync_renews_when_period_advances(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);

        $sub = app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'lemonsqueezy_subscription_id' => 'ls_2',
            'renews_at' => now()->addDays(30),
        ]);
        $sub->update(['credits_remaining' => 4]); // simulate consumption

        $newRenews = now()->addDays(60);
        app(SubscriptionProvisioner::class)->syncFromLemonSqueezy('ls_2', $plan, 'monthly', $newRenews, 'active');

        $sub->refresh();
        $this->assertSame(100, $sub->credits_remaining);
        $this->assertEquals($newRenews->toDateString(), $sub->ends_at->toDateString());
    }

    public function test_sync_upgrades_credits_when_plan_changes(): void
    {
        $user = User::factory()->create();
        $starter = $this->plan('Starter', 100);
        $pro = $this->plan('Pro', 500);

        $sub = app(SubscriptionProvisioner::class)->provision($user, $starter, [
            'lemonsqueezy_subscription_id' => 'ls_3',
            'renews_at' => now()->addDays(30),
        ]);

        // Same period, but the incoming plan differs (an upgrade took effect).
        app(SubscriptionProvisioner::class)->syncFromLemonSqueezy('ls_3', $pro, 'monthly', $sub->renews_at, 'active');

        $sub->refresh();
        $this->assertSame($pro->id, $sub->plan_id);
        $this->assertSame(500, $sub->credits_remaining);
    }

    public function test_sync_is_noop_when_same_plan_and_same_period(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);

        $sub = app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'lemonsqueezy_subscription_id' => 'ls_4',
            'renews_at' => now()->addDays(30),
        ]);
        $sub->update(['credits_remaining' => 2]);

        app(SubscriptionProvisioner::class)->syncFromLemonSqueezy('ls_4', $plan, 'monthly', $sub->renews_at, 'active');

        $this->assertSame(2, $sub->refresh()->credits_remaining); // untouched
    }

    public function test_provision_yearly_grants_annual_credit_bundle(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Starter', 'credit_limit' => 100, 'yearly_credit_limit' => 1200,
            'price' => 10, 'yearly_price' => 108, 'duration_days' => 30, 'is_active' => true,
        ]);

        $sub = app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'interval' => 'yearly',
            'lemonsqueezy_subscription_id' => 'ls_y',
            'renews_at' => now()->addYear(),
        ]);

        $this->assertSame('yearly', $sub->billing_interval);
        $this->assertSame(1200, $sub->credits_remaining);
        $this->assertTrue($sub->ends_at->greaterThan(now()->addMonths(11)));
    }

    public function test_sync_switches_interval_monthly_to_yearly(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Starter', 'credit_limit' => 100, 'yearly_credit_limit' => 1200,
            'price' => 10, 'yearly_price' => 108, 'duration_days' => 30, 'is_active' => true,
        ]);

        $sub = app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'interval' => 'monthly',
            'lemonsqueezy_subscription_id' => 'ls_sw',
            'renews_at' => now()->addDays(30),
        ]);

        // Same plan, same period, but interval changes to yearly (upgrade to yearly).
        app(SubscriptionProvisioner::class)->syncFromLemonSqueezy('ls_sw', $plan, 'yearly', $sub->renews_at, 'active');

        $sub->refresh();
        $this->assertSame('yearly', $sub->billing_interval);
        $this->assertSame(1200, $sub->credits_remaining);
    }

    public function test_revoke_expires_the_subscription(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);

        app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'lemonsqueezy_subscription_id' => 'ls_5',
            'renews_at' => now()->addDays(30),
        ]);

        app(SubscriptionProvisioner::class)->revoke('ls_5');

        $sub = Subscription::where('lemonsqueezy_subscription_id', 'ls_5')->first();
        $this->assertSame(Subscription::STATUS_EXPIRED, $sub->status);
    }
}
