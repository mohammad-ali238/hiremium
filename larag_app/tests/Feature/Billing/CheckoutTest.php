<?php

namespace Tests\Feature\Billing;

use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BillingSetting::current()->update([
            'enabled' => true, 'store_id' => '42', 'api_key' => 'key', 'signing_secret' => 'sec',
        ]);
    }

    private function plan(string $name, int $credits, ?string $variant = 'var_x'): Plan
    {
        return Plan::create([
            'name' => $name, 'credit_limit' => $credits, 'price' => 10,
            'duration_days' => 30, 'is_active' => true, 'lemonsqueezy_variant_id' => $variant,
        ]);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function activeSub(User $user, Plan $plan, array $extra = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => $user->id, 'plan_id' => $plan->id,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30),
            'credits_remaining' => 50, 'status' => Subscription::STATUS_ACTIVE,
        ], $extra));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $plan = $this->plan('Starter', 100);
        $this->post('/billing/checkout', ['plan_id' => $plan->id])->assertRedirect('/login');
    }

    public function test_new_subscriber_is_redirected_to_lemonsqueezy_checkout(): void
    {
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => ['url' => 'https://store.lemonsqueezy.com/checkout/abc']]], 201),
        ]);

        $user = $this->verifiedUser();
        $plan = $this->plan('Starter', 100);

        $this->actingAs($user)
            ->post('/billing/checkout', ['plan_id' => $plan->id])
            ->assertRedirect('https://store.lemonsqueezy.com/checkout/abc');
    }

    public function test_same_or_lower_plan_is_rejected_for_active_subscriber(): void
    {
        $user = $this->verifiedUser();
        $starter = $this->plan('Starter', 100);
        $lower = $this->plan('Mini', 50);
        $this->activeSub($user, $starter, ['lemonsqueezy_subscription_id' => 'ls_a']);

        // Same plan.
        $this->actingAs($user)->post('/billing/checkout', ['plan_id' => $starter->id])
            ->assertRedirect(route('pricing'))->assertSessionHas('error');

        // Lower plan.
        $this->actingAs($user)->post('/billing/checkout', ['plan_id' => $lower->id])
            ->assertRedirect(route('pricing'))->assertSessionHas('error');
    }

    public function test_upgrade_redirects_to_a_new_checkout(): void
    {
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => ['url' => 'https://store.lemonsqueezy.com/checkout/up']]], 201),
        ]);

        $user = $this->verifiedUser();
        $starter = $this->plan('Starter', 100);
        $pro = $this->plan('Pro', 500, '777');
        $this->activeSub($user, $starter, ['lemonsqueezy_subscription_id' => 'ls_up']);

        $this->actingAs($user)
            ->post('/billing/checkout', ['plan_id' => $pro->id])
            ->assertRedirect('https://store.lemonsqueezy.com/checkout/up');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkouts')
            && data_get($request->data(), 'data.relationships.variant.data.id') === '777');
    }

    public function test_new_subscriber_yearly_uses_yearly_variant(): void
    {
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => ['url' => 'https://store.lemonsqueezy.com/checkout/yr']]], 201),
        ]);

        $user = $this->verifiedUser();
        $plan = Plan::create([
            'name' => 'Starter', 'credit_limit' => 100, 'yearly_credit_limit' => 1200,
            'price' => 10, 'yearly_price' => 108, 'duration_days' => 30, 'is_active' => true,
            'lemonsqueezy_variant_id' => 'v_m', 'lemonsqueezy_yearly_variant_id' => 'v_y',
        ]);

        $this->actingAs($user)
            ->post('/billing/checkout', ['plan_id' => $plan->id, 'interval' => 'yearly'])
            ->assertRedirect('https://store.lemonsqueezy.com/checkout/yr');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkouts')
            && data_get($request->data(), 'data.relationships.variant.data.id') === 'v_y'
            && data_get($request->data(), 'data.attributes.checkout_data.custom.interval') === 'yearly');
    }

    public function test_same_tier_monthly_to_yearly_redirects_to_checkout_with_yearly_variant(): void
    {
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => ['url' => 'https://store.lemonsqueezy.com/checkout/yr']]], 201),
        ]);

        $user = $this->verifiedUser();
        $plan = Plan::create([
            'name' => 'Starter', 'credit_limit' => 100, 'yearly_credit_limit' => 1200,
            'price' => 10, 'yearly_price' => 108, 'duration_days' => 30, 'is_active' => true,
            'lemonsqueezy_variant_id' => '111', 'lemonsqueezy_yearly_variant_id' => '999',
        ]);
        $this->activeSub($user, $plan, ['lemonsqueezy_subscription_id' => 'ls_sw', 'billing_interval' => 'monthly']);

        $this->actingAs($user)
            ->post('/billing/checkout', ['plan_id' => $plan->id, 'interval' => 'yearly'])
            ->assertRedirect('https://store.lemonsqueezy.com/checkout/yr');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkouts')
            && data_get($request->data(), 'data.relationships.variant.data.id') === '999'
            && data_get($request->data(), 'data.attributes.checkout_data.custom.interval') === 'yearly');
    }

    public function test_same_tier_yearly_to_monthly_is_blocked(): void
    {
        $user = $this->verifiedUser();
        $plan = Plan::create([
            'name' => 'Starter', 'credit_limit' => 100, 'yearly_credit_limit' => 1200,
            'price' => 10, 'yearly_price' => 108, 'duration_days' => 30, 'is_active' => true,
            'lemonsqueezy_variant_id' => 'v_m', 'lemonsqueezy_yearly_variant_id' => 'v_y',
        ]);
        $this->activeSub($user, $plan, ['lemonsqueezy_subscription_id' => 'ls_yr', 'billing_interval' => 'yearly']);

        $this->actingAs($user)->post('/billing/checkout', ['plan_id' => $plan->id, 'interval' => 'monthly'])
            ->assertRedirect(route('pricing'))->assertSessionHas('error');
    }

    public function test_plan_without_variant_is_rejected(): void
    {
        $user = $this->verifiedUser();
        $plan = $this->plan('NoVariant', 100, null);

        $this->actingAs($user)->post('/billing/checkout', ['plan_id' => $plan->id])
            ->assertRedirect(route('pricing'))->assertSessionHas('error');
    }
}
