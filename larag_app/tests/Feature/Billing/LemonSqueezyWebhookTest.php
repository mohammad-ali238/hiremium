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

class LemonSqueezyWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_123';

    protected function setUp(): void
    {
        parent::setUp();

        BillingSetting::current()->update([
            'enabled' => true, 'store_id' => '42', 'api_key' => 'key', 'signing_secret' => self::SECRET,
        ]);

        // Stub any outbound LS call (e.g. cancelling a superseded subscription).
        Http::fake(['api.lemonsqueezy.com/*' => Http::response([], 204)]);
    }

    private function plan(string $name, int $credits, string $variant, array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'name' => $name, 'credit_limit' => $credits, 'price' => 10,
            'duration_days' => 30, 'is_active' => true, 'lemonsqueezy_variant_id' => $variant,
        ], $extra));
    }

    private function send(array $body, string $event, ?string $secret = null)
    {
        $json = json_encode($body);
        $sig = hash_hmac('sha256', $json, $secret ?? self::SECRET);

        return $this->call('POST', '/api/webhooks/lemonsqueezy', [], [], [], [
            'HTTP_X_SIGNATURE' => $sig,
            'HTTP_X_EVENT_NAME' => $event,
            'CONTENT_TYPE' => 'application/json',
        ], $json);
    }

    /** Subscription-object payload (created / updated / cancelled / expired). */
    private function sub(string $event, string $lsSub, string $variant, string $renewsAt, array $custom = [], array $extra = []): array
    {
        return [
            'meta' => ['event_name' => $event, 'custom_data' => $custom],
            'data' => [
                'id' => $lsSub,
                'attributes' => array_merge([
                    'variant_id' => $variant,
                    'status' => 'active',
                    'renews_at' => $renewsAt,
                    'customer_id' => 'cust_1',
                    'order_id' => 'ord_'.$lsSub,
                ], $extra),
            ],
        ];
    }

    /** Invoice payload (payment_success / payment_failed). data.id = invoice id. */
    private function invoice(string $event, string $invoiceId, string $subId, int $total = 1500, string $status = 'paid'): array
    {
        return [
            'meta' => ['event_name' => $event, 'custom_data' => []],
            'data' => [
                'id' => $invoiceId,
                'attributes' => [
                    'subscription_id' => $subId,
                    'total' => $total,
                    'currency' => 'USD',
                    'customer_id' => 'cust_1',
                    'status' => $status,
                ],
            ],
        ];
    }

    public function test_bad_signature_is_rejected(): void
    {
        $body = $this->sub('subscription_created', 'ls_10', 'var_1', now()->addDays(30)->toIso8601String());
        $this->send($body, 'subscription_created', 'wrong-secret')->assertStatus(403);
    }

    public function test_webhook_rejected_when_billing_disabled(): void
    {
        BillingSetting::current()->update(['enabled' => false]);
        $body = $this->sub('subscription_created', 'ls_15', 'var_1', now()->addDays(30)->toIso8601String());
        $this->send($body, 'subscription_created')->assertStatus(403);
    }

    public function test_subscription_created_provisions_and_records_one_success_order(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');

        $this->send($this->sub('subscription_created', 'ls_11', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        $sub = Subscription::where('lemonsqueezy_subscription_id', 'ls_11')->first();
        $this->assertNotNull($sub);
        $this->assertSame(100, $sub->credits_remaining);

        // Exactly one success order for the purchase.
        $this->assertSame(1, PaymentTransaction::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('payment_transactions', ['lemonsqueezy_subscription_id' => 'ls_11', 'status' => 'success']);

        // A duplicate created webhook does not add a second order.
        $this->send($this->sub('subscription_created', 'ls_11', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();
        $this->assertSame(1, PaymentTransaction::where('user_id', $user->id)->count());
    }

    public function test_subscription_created_on_yearly_variant_grants_annual_bundle(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_m', [
            'yearly_credit_limit' => 1200, 'yearly_price' => 108, 'lemonsqueezy_yearly_variant_id' => 'var_y',
        ]);

        $this->send($this->sub('subscription_created', 'ls_y1', 'var_y', now()->addYear()->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        $sub = Subscription::where('lemonsqueezy_subscription_id', 'ls_y1')->first();
        $this->assertSame('yearly', $sub->billing_interval);
        $this->assertSame(1200, $sub->credits_remaining);
    }

    public function test_subscription_updated_renewal_resets_credits(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');

        $this->send($this->sub('subscription_created', 'ls_12', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();
        Subscription::where('lemonsqueezy_subscription_id', 'ls_12')->update(['credits_remaining' => 5]);

        // Renewal advances renews_at → credits reset.
        $this->send($this->sub('subscription_updated', 'ls_12', 'var_1', now()->addDays(60)->toIso8601String()), 'subscription_updated')->assertOk();

        $this->assertSame(100, Subscription::where('lemonsqueezy_subscription_id', 'ls_12')->first()->credits_remaining);
    }

    public function test_updated_variant_upgrade_switches_plan_and_credits(): void
    {
        $user = User::factory()->create();
        $starter = $this->plan('Starter', 100, 'var_1');
        $pro = $this->plan('Pro', 500, 'var_2');

        $renews = now()->addDays(30)->toIso8601String();
        $this->send($this->sub('subscription_created', 'ls_13', 'var_1', $renews, ['user_id' => (string) $user->id, 'plan_id' => (string) $starter->id]), 'subscription_created')->assertOk();
        $this->send($this->sub('subscription_updated', 'ls_13', 'var_2', $renews), 'subscription_updated')->assertOk();

        $sub = Subscription::where('lemonsqueezy_subscription_id', 'ls_13')->first();
        $this->assertSame($pro->id, $sub->plan_id);
        $this->assertSame(500, $sub->credits_remaining);
    }

    public function test_initial_payment_does_not_add_a_second_row_when_created_already_has_amount(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');

        // subscription_created already carries the price (as real LS payloads do).
        $this->send($this->sub('subscription_created', 'ls_ps', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id], ['total' => 1500, 'currency' => 'USD']), 'subscription_created')->assertOk();

        $this->assertSame(1500, PaymentTransaction::where('user_id', $user->id)->first()->amount_total);

        // The following initial payment must NOT create a second row.
        $invoice = $this->invoice('subscription_payment_success', 'inv_1', 'ls_ps', 1500);
        $invoice['data']['attributes']['billing_reason'] = 'initial';
        $this->send($invoice, 'subscription_payment_success')->assertOk();

        $this->assertSame(1, PaymentTransaction::where('user_id', $user->id)->count());
    }

    public function test_initial_payment_fills_amount_when_created_had_none(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $this->send($this->sub('subscription_created', 'ls_ps2', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        $this->assertNull(PaymentTransaction::where('user_id', $user->id)->first()->amount_total);

        $invoice = $this->invoice('subscription_payment_success', 'inv_2', 'ls_ps2', 1500);
        $invoice['data']['attributes']['billing_reason'] = 'initial';
        $this->send($invoice, 'subscription_payment_success')->assertOk();

        $this->assertSame(1, PaymentTransaction::where('user_id', $user->id)->count());
        $this->assertSame(1500, PaymentTransaction::where('user_id', $user->id)->first()->amount_total);
    }

    public function test_renewal_payment_adds_a_new_success_order(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $this->send($this->sub('subscription_created', 'ls_rn', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        // Renewal charge → a new success order row.
        $invoice = $this->invoice('subscription_payment_success', 'inv_renew', 'ls_rn', 1500);
        $invoice['data']['attributes']['billing_reason'] = 'renewal';
        $this->send($invoice, 'subscription_payment_success')->assertOk();

        $this->assertSame(2, PaymentTransaction::where('user_id', $user->id)->where('status', 'success')->count());
        $this->assertDatabaseHas('payment_transactions', ['lemonsqueezy_order_id' => 'inv_renew', 'amount_total' => 1500]);

        // Duplicate renewal delivery → no extra row.
        $this->send($invoice, 'subscription_payment_success')->assertOk();
        $this->assertSame(2, PaymentTransaction::where('user_id', $user->id)->where('status', 'success')->count());
    }

    public function test_cancelled_keeps_access_syncs_ends_at_and_flags_the_order(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $this->send($this->sub('subscription_created', 'ls_16', 'var_1', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        $lsEndsAt = now()->addDays(12);
        $this->send($this->sub('subscription_cancelled', 'ls_16', 'var_1', now()->addDays(30)->toIso8601String(), [], ['status' => 'cancelled', 'ends_at' => $lsEndsAt->toIso8601String()]), 'subscription_cancelled')->assertOk();

        $sub = Subscription::where('lemonsqueezy_subscription_id', 'ls_16')->first();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame('cancelled', $sub->lemonsqueezy_status);
        $this->assertEquals($lsEndsAt->toDateString(), $sub->ends_at->toDateString());

        // No separate cancelled row — the existing order is flagged instead.
        $this->assertDatabaseMissing('payment_transactions', ['event_name' => 'subscription_cancelled']);
        $order = PaymentTransaction::where('lemonsqueezy_subscription_id', 'ls_16')->latest('id')->first();
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_resume_clears_the_cancellation_flag_on_the_order(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $renews = now()->addDays(30)->toIso8601String();
        $this->send($this->sub('subscription_created', 'ls_17', 'var_1', $renews, ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();
        $this->send($this->sub('subscription_cancelled', 'ls_17', 'var_1', $renews, [], ['status' => 'cancelled', 'ends_at' => now()->addDays(12)->toIso8601String()]), 'subscription_cancelled')->assertOk();
        $this->assertNotNull(PaymentTransaction::where('lemonsqueezy_subscription_id', 'ls_17')->latest('id')->first()->cancelled_at);

        // Resume: LemonSqueezy sends subscription_updated with status active.
        $this->send($this->sub('subscription_updated', 'ls_17', 'var_1', $renews, [], ['status' => 'active']), 'subscription_updated')->assertOk();

        $this->assertNull(PaymentTransaction::where('lemonsqueezy_subscription_id', 'ls_17')->latest('id')->first()->cancelled_at);
        $this->assertSame('active', Subscription::where('lemonsqueezy_subscription_id', 'ls_17')->first()->lemonsqueezy_status);
    }

    public function test_payment_failed_records_a_failed_order_without_assigning_credits(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $renews = now()->addDays(30)->toIso8601String();
        $this->send($this->sub('subscription_created', 'ls_pf', 'var_1', $renews, ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        // Consume some credits, then a renewal payment fails.
        $sub = Subscription::where('lemonsqueezy_subscription_id', 'ls_pf')->first();
        $sub->update(['credits_remaining' => 30]);

        $this->send($this->invoice('subscription_payment_failed', 'inv_pf', 'ls_pf', 1500, 'failed'), 'subscription_payment_failed')->assertOk();

        // A failed order row exists...
        $this->assertDatabaseHas('payment_transactions', [
            'lemonsqueezy_subscription_id' => 'ls_pf', 'event_name' => 'subscription_payment_failed', 'status' => 'failed',
        ]);
        // ...and credits were NOT topped up (still 30, not reset to the plan limit).
        $sub->refresh();
        $this->assertSame(30, $sub->credits_remaining);
        // ...and the subscription is flagged past-due for the dashboard warning.
        $this->assertSame('past_due', $sub->lemonsqueezy_status);
    }

    public function test_payment_success_clears_a_past_due_flag(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $renews = now()->addDays(30)->toIso8601String();
        $this->send($this->sub('subscription_created', 'ls_pd', 'var_1', $renews, ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        Subscription::where('lemonsqueezy_subscription_id', 'ls_pd')->update(['lemonsqueezy_status' => 'past_due']);

        $this->send($this->invoice('subscription_payment_success', 'inv_ok', 'ls_pd', 1500, 'paid'), 'subscription_payment_success')->assertOk();

        $this->assertSame('active', Subscription::where('lemonsqueezy_subscription_id', 'ls_pd')->first()->lemonsqueezy_status);
    }

    public function test_expired_revokes_access(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100, 'var_1');
        $renews = now()->addDays(30)->toIso8601String();
        $this->send($this->sub('subscription_created', 'ls_14', 'var_1', $renews, ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]), 'subscription_created')->assertOk();

        $this->send($this->sub('subscription_expired', 'ls_14', 'var_1', $renews), 'subscription_expired')->assertOk();

        $this->assertSame(Subscription::STATUS_EXPIRED, Subscription::where('lemonsqueezy_subscription_id', 'ls_14')->first()->status);
    }

    public function test_upgrade_via_new_subscription_cancels_the_old_one(): void
    {
        $user = User::factory()->create();
        $starter = $this->plan('Starter', 100, 'var_1');
        $pro = $this->plan('Pro', 500, 'var_2');

        // Existing active subscription on LS id ls_old.
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $starter->id, 'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(20), 'credits_remaining' => 40, 'status' => Subscription::STATUS_ACTIVE,
            'billing_interval' => 'monthly', 'lemonsqueezy_subscription_id' => 'ls_old',
        ]);

        // New subscription created (the upgrade checkout completed).
        $this->send($this->sub('subscription_created', 'ls_new', 'var_2', now()->addDays(30)->toIso8601String(), ['user_id' => (string) $user->id, 'plan_id' => (string) $pro->id]), 'subscription_created')->assertOk();

        $this->assertSame(Subscription::STATUS_SUPERSEDED, Subscription::where('lemonsqueezy_subscription_id', 'ls_old')->first()->status);
        $this->assertSame(500, Subscription::where('lemonsqueezy_subscription_id', 'ls_new')->first()->credits_remaining);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/subscriptions/ls_old') && $r->method() === 'DELETE');
    }
}
