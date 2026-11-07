<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    private function enableRecaptcha(): void
    {
        SiteSettings::setRecaptchaEnabled(true);
        SiteSettings::setRecaptchaSiteKey('test-site-key');
        SiteSettings::setRecaptchaSecretKey('test-secret-key');
    }

    public function test_registration_works_with_captcha_disabled(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'email_canonical' => 'newuser@example.com',
        ]);
    }

    public function test_registration_succeeds_with_valid_captcha(): void
    {
        $this->enableRecaptcha();
        Http::fake([
            'www.google.com/recaptcha/*' => Http::response(['success' => true]),
        ]);

        $response = $this->post('/register', $this->validPayload([
            'g-recaptcha-response' => 'valid-token',
        ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_registration_fails_without_captcha_token_when_enabled(): void
    {
        $this->enableRecaptcha();

        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    }

    public function test_registration_fails_when_google_rejects_captcha(): void
    {
        $this->enableRecaptcha();
        Http::fake([
            'www.google.com/recaptcha/*' => Http::response(['success' => false]),
        ]);

        $response = $this->post('/register', $this->validPayload([
            'g-recaptcha-response' => 'bad-token',
        ]));

        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    }

    public function test_disposable_email_is_rejected(): void
    {
        $response = $this->post('/register', $this->validPayload([
            'email' => 'bot@mailinator.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disposable_email_subdomain_is_rejected(): void
    {
        $response = $this->post('/register', $this->validPayload([
            'email' => 'bot@sub.mailinator.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_canonical_email_alias_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'john.doe@gmail.com',
            'email_canonical' => 'johndoe@gmail.com',
        ]);

        $response = $this->post('/register', $this->validPayload([
            'email' => 'johndoe+alias@gmail.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_fourth_registration_from_same_ip_in_a_day_is_rejected(): void
    {
        User::factory()->count(3)->create([
            'registration_ip' => '127.0.0.1',
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_closed_toggle_blocks_signup(): void
    {
        SiteSettings::setRegistrationEnabled(false);

        $this->get('/register')->assertRedirect(route('login'));
        $this->post('/register', $this->validPayload())->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_user_must_verify_email_before_dashboard(): void
    {
        $this->post('/register', $this->validPayload());

        $this->assertAuthenticated();

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('verification.notice'));
    }
}
