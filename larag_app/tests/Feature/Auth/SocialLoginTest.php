<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function enableProvider(string $provider): void
    {
        SiteSettings::setSocialEnabled($provider, true);
        SiteSettings::setSocialClientId($provider, 'client-id');
        SiteSettings::setSocialClientSecret($provider, 'client-secret');
    }

    private function fakeSocialiteUser(string $id = 'g-123', string $email = 'social@example.com', string $name = 'Social User'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);

        return $user;
    }

    private function mockCallback(string $driver, SocialiteUser $socialUser): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
    }

    public function test_disabled_provider_returns_404(): void
    {
        $response = $this->get('/auth/google/redirect');

        $response->assertNotFound();
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->enableProvider('google');

        $response = $this->get('/auth/github/redirect');

        $response->assertNotFound();
    }

    public function test_enabled_provider_redirects_to_consent_screen(): void
    {
        $this->enableProvider('google');

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_callback_creates_new_verified_user_and_logs_in(): void
    {
        $this->enableProvider('google');
        $this->mockCallback('google', $this->fakeSocialiteUser());

        $response = $this->get('/auth/google/callback?code=fake&state=fake');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'social@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google', $user->provider_name);
        $this->assertSame('g-123', $user->provider_id);
        $this->assertNotNull($user->email_verified_at, 'provider-verified email should be marked verified');
        $this->assertSame('social@example.com', $user->email_canonical);
    }

    public function test_callback_links_provider_to_existing_email_account(): void
    {
        $this->enableProvider('google');

        $existing = User::factory()->create([
            'email' => 'social@example.com',
            'provider_name' => null,
            'provider_id' => null,
        ]);

        $this->mockCallback('google', $this->fakeSocialiteUser());

        $response = $this->get('/auth/google/callback?code=fake&state=fake');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);

        $existing->refresh();
        $this->assertSame('google', $existing->provider_name);
        $this->assertSame('g-123', $existing->provider_id);
        $this->assertSame(1, User::count(), 'no duplicate account created');
    }

    public function test_callback_logs_in_returning_social_user(): void
    {
        $this->enableProvider('google');

        $existing = User::factory()->create([
            'email' => 'social@example.com',
            'provider_name' => 'google',
            'provider_id' => 'g-123',
        ]);

        $this->mockCallback('google', $this->fakeSocialiteUser());

        $response = $this->get('/auth/google/callback?code=fake&state=fake');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
    }

    public function test_callback_blocks_new_accounts_when_registration_closed(): void
    {
        $this->enableProvider('google');
        SiteSettings::setRegistrationEnabled(false);

        $this->mockCallback('google', $this->fakeSocialiteUser());

        $response = $this->get('/auth/google/callback?code=fake&state=fake');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_callback_allows_existing_social_user_when_registration_closed(): void
    {
        $this->enableProvider('google');
        SiteSettings::setRegistrationEnabled(false);

        $existing = User::factory()->create([
            'email' => 'social@example.com',
            'provider_name' => 'google',
            'provider_id' => 'g-123',
        ]);

        $this->mockCallback('google', $this->fakeSocialiteUser());

        $response = $this->get('/auth/google/callback?code=fake&state=fake');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);
    }

    public function test_callback_without_email_redirects_with_error(): void
    {
        $this->enableProvider('google');

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('g-999');
        $socialUser->shouldReceive('getEmail')->andReturn(null);
        $socialUser->shouldReceive('getName')->andReturn('No Email');

        $this->mockCallback('google', $socialUser);

        $response = $this->get('/auth/google/callback?code=fake&state=fake');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_linkedin_slug_maps_to_openid_driver(): void
    {
        $this->enableProvider('linkedin');
        $this->mockCallback('linkedin-openid', $this->fakeSocialiteUser('li-1', 'li@example.com', 'Li User'));

        $response = $this->get('/auth/linkedin/callback?code=fake&state=fake');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'li@example.com',
            'provider_name' => 'linkedin',
            'provider_id' => 'li-1',
        ]);
    }
}
