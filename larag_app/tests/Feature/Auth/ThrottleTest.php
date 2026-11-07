<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_post_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', []); // validation failures still count against throttle
        }

        $response = $this->post('/register', []);

        $response->assertStatus(429);
    }

    public function test_login_post_is_throttled_after_ten_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            // Different emails each time so LoginRequest's own 5-attempt lockout
            // (keyed by email+IP) never fires first — this tests the route throttle.
            $this->post('/login', ['email' => "u{$i}@example.com", 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => 'u11@example.com', 'password' => 'wrong']);

        $response->assertStatus(429);
    }

    public function test_forgot_password_post_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        }

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertStatus(429);
    }

    public function test_api_token_generation_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('/api-token');
        }

        $response = $this->actingAs($user)->post('/api-token');

        $response->assertStatus(429);
    }
}
