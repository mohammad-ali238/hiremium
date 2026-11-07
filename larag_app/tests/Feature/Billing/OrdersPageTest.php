<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/my-orders')->assertRedirect('/login');
    }

    public function test_user_sees_only_their_own_orders(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        PaymentTransaction::create([
            'user_id' => $user->id, 'event_name' => 'subscription_created', 'status' => 'active',
            'amount_total' => 10800, 'currency' => 'usd', 'lemonsqueezy_order_id' => 'MINE1', 'payload' => [],
        ]);
        PaymentTransaction::create([
            'user_id' => $other->id, 'event_name' => 'subscription_created', 'status' => 'active',
            'amount_total' => 999, 'currency' => 'usd', 'lemonsqueezy_order_id' => 'THEIRS1', 'payload' => [],
        ]);

        $this->actingAs($user)->get('/my-orders')
            ->assertOk()
            ->assertSee('MINE1')
            ->assertDontSee('THEIRS1');
    }

    public function test_orders_paginate_ten_per_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 12) as $n) {
            PaymentTransaction::create([
                'user_id' => $user->id, 'event_name' => 'subscription_payment_success', 'status' => 'active',
                'amount_total' => 1000, 'currency' => 'usd', 'lemonsqueezy_order_id' => 'O'.$n, 'payload' => [],
            ]);
        }

        $this->actingAs($user)->get('/my-orders')->assertOk()->assertSee('Next'); // paginator link present
    }
}
