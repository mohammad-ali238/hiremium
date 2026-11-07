<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountSignals;
use App\Support\SiteSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class SocialLoginController extends Controller
{
    /**
     * Public URL slug => Socialite driver name. LinkedIn now requires their
     * OpenID Connect flow, hence the linkedin-openid driver.
     */
    private const DRIVERS = [
        'google' => 'google',
        'facebook' => 'facebook',
        'linkedin' => 'linkedin-openid',
    ];

    /**
     * Send the user to the provider's consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $driver = $this->resolveDriver($provider);

        return Socialite::driver($driver)->redirect();
    }

    /**
     * Handle the provider callback: log in an existing social account, link the
     * provider to an existing email, or register a new user.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->resolveDriver($provider);

        try {
            $socialUser = Socialite::driver($driver)->user();
        } catch (Throwable $e) {
            Log::info('Social login callback failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return redirect()->route('login')
                ->withErrors(['email' => 'Social sign-in was cancelled or failed. Please try again.']);
        }

        $email = mb_strtolower(trim((string) $socialUser->getEmail()));
        $providerId = (string) $socialUser->getId();

        if ($email === '' || $providerId === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your social account did not share an email address. Please register with email instead.']);
        }

        // 1. Same provider account seen before → log straight in.
        $user = User::where('provider_name', $provider)
            ->where('provider_id', $providerId)
            ->first();

        // 2. Known email → link this provider to the existing account.
        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->forceFill([
                    'provider_name' => $provider,
                    'provider_id' => $providerId,
                    // The provider has verified this mailbox.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }
        }

        // 3. Brand-new user → register (respecting the admin's registration toggle).
        if (! $user) {
            if (! SiteSettings::registrationEnabled()) {
                return redirect()->route('login')
                    ->with('status', 'New account registration is currently closed.');
            }

            // Same multi-account guards as email registration: an alias of an
            // existing inbox, or too many sign-ups from this network/browser.
            $deviceHash = AccountSignals::deviceHash($request->cookie(AccountSignals::DEVICE_COOKIE));

            if (User::where('email_canonical', AccountSignals::canonicalEmail($email))->exists()) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'An account already exists for this email address. Please log in with your email and password.']);
            }

            $ipCapHit = User::where('registration_ip', $request->ip())
                ->where('created_at', '>=', now()->subDay())
                ->count() >= AccountSignals::MAX_PER_IP_PER_DAY;
            $deviceCapHit = $deviceHash
                && User::where('device_hash', $deviceHash)->count() >= AccountSignals::MAX_PER_DEVICE;

            if ($ipCapHit || $deviceCapHit) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Registration is temporarily unavailable. Please try again later.']);
            }

            $user = User::create([
                'name' => (string) ($socialUser->getName() ?: Str::before($email, '@')),
                'email' => $email,
                // Social accounts have no local password; store a random one.
                'password' => Hash::make(Str::random(40)),
                'email_canonical' => AccountSignals::canonicalEmail($email),
                'registration_ip' => $request->ip(),
                'device_hash' => $deviceHash,
                'provider_name' => $provider,
                'provider_id' => $providerId,
                'email_verified_at' => now(),
            ]);

            event(new Registered($user));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Validate the provider slug, ensure it is enabled, and inject its
     * credentials from Site Settings into Socialite's runtime config.
     */
    private function resolveDriver(string $provider): string
    {
        if (! array_key_exists($provider, self::DRIVERS) || ! SiteSettings::socialEnabled($provider)) {
            throw new NotFoundHttpException;
        }

        $driver = self::DRIVERS[$provider];

        config([
            "services.{$driver}" => [
                'client_id' => SiteSettings::socialClientId($provider),
                'client_secret' => SiteSettings::socialClientSecret($provider),
                'redirect' => route('social.callback', ['provider' => $provider]),
            ],
        ]);

        return $driver;
    }
}
