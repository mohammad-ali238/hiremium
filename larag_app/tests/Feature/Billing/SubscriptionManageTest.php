<?php

namespace Tests\Feature\Billing;

use App\Models\BillingSetting;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BillingSetting::current()->update([
            'enabled' => true, 'store_id' => '42', 'api_key' => 'key', 'signing_secret' => 'sec',
        ]);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Starter', 'credit_limit' => 100, 'price' => 10,
            'duration_days' => 30, 'is_active' => true, 'lemonsqueezy_variant_id' => 'var_x',
        ]);
    }

    private function activeSub(User $user, Plan $plan, array $extra = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => $user->id, 'plan_id' => $plan->id,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(20),
            'credits_remaining' => 50, 'status' => Subscription::STATUS_ACTIVE,
            'billing_interval' => 'monthly',
        ], $extra));
    }

    public function test_cancel_calls_lemonsqueezy_and_marks_subscription_cancelled(): void
    {
        $endsAt = now()->addDays(20)->startOfSecond();
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => [
                'status' => 'cancelled', 'ends_at' => $endsAt->toIso8601String(),
            ]]], 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->activeSub($user, $this->plan(), ['lemonsqueezy_subscription_id' => 'ls_1', 'lemonsqueezy_status' => 'active']);
        $order = PaymentTransaction::create([
            'user_id' => $user->id, 'subscription_id' => $sub->id, 'plan_id' => $sub->plan_id,
            'lemonsqueezy_subscription_id' => 'ls_1', 'event_name' => 'subscription_created',
            'status' => 'success', 'amount_total' => 1000, 'currency' => 'USD', 'payload' => [],
        ]);

        $this->actingAs($user)->post('/billing/subscription/cancel')
            ->assertRedirect(route('app.billing'))
            ->assertSessionHas('billing_status');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/subscriptions/ls_1'));

        $sub->refresh();
        $this->assertSame('cancelled', $sub->lemonsqueezy_status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status, 'access is kept until period end');
        $this->assertEquals($endsAt->toDateString(), $sub->ends_at->toDateString());

        // The existing order entry is flagged — no new cancelled row is created.
        $this->assertNotNull($order->refresh()->cancelled_at);
        $this->assertSame(1, PaymentTransaction::where('lemonsqueezy_subscription_id', 'ls_1')->count());
    }

    public function test_resume_calls_lemonsqueezy_and_reactivates(): void
    {
        $renewsAt = now()->addDays(20)->startOfSecond();
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => [
                'status' => 'active', 'renews_at' => $renewsAt->toIso8601String(),
            ]]], 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->activeSub($user, $this->plan(), ['lemonsqueezy_subscription_id' => 'ls_2', 'lemonsqueezy_status' => 'cancelled']);

        // The order entry is currently flagged as cancelled.
        $order = PaymentTransaction::create([
            'user_id' => $user->id, 'subscription_id' => $sub->id, 'plan_id' => $sub->plan_id,
            'lemonsqueezy_subscription_id' => 'ls_2', 'event_name' => 'subscription_created',
            'status' => 'success', 'cancelled_at' => now()->subDay(), 'payload' => [],
        ]);

        $this->actingAs($user)->post('/billing/subscription/resume')
            ->assertRedirect(route('app.billing'))
            ->assertSessionHas('billing_status');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/subscriptions/ls_2')
            && data_get($request->data(), 'data.attributes.cancelled') === false);

        $this->assertSame('active', $sub->refresh()->lemonsqueezy_status);
        $this->assertNull($order->refresh()->cancelled_at, 'the cancellation flag is cleared on resume');
    }

    public function test_cancel_without_lemonsqueezy_id_is_rejected(): void
    {
        Http::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->activeSub($user, $this->plan(), ['lemonsqueezy_subscription_id' => null]);

        $this->actingAs($user)->post('/billing/subscription/cancel')
            ->assertRedirect(route('app.billing'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_resume_on_non_cancelled_subscription_is_rejected(): void
    {
        Http::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->activeSub($user, $this->plan(), ['lemonsqueezy_subscription_id' => 'ls_3', 'lemonsqueezy_status' => 'active']);

        $this->actingAs($user)->post('/billing/subscription/resume')
            ->assertRedirect(route('app.billing'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_update_payment_redirects_to_a_fresh_lemonsqueezy_url(): void
    {
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => ['urls' => [
                'update_payment_method' => 'https://store.lemonsqueezy.com/subscription/1/payment-details?expires=999',
            ]]]], 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->activeSub($user, $this->plan(), ['lemonsqueezy_subscription_id' => 'ls_pd', 'lemonsqueezy_status' => 'past_due']);

        $this->actingAs($user)->post('/billing/subscription/payment-method')
            ->assertRedirect('https://store.lemonsqueezy.com/subscription/1/payment-details?expires=999');

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/subscriptions/ls_pd'));
    }

    public function test_guest_cannot_cancel(): void
    {
        $this->post('/billing/subscription/cancel')->assertRedirect('/login');
    }
}
