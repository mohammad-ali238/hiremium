<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Recaptcha;
use App\Services\Workspaces\WorkspaceProvisioner;
use App\Support\AccountSignals;
use App\Support\SiteSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'recaptchaEnabled' => SiteSettings::recaptchaEnabled(),
            'recaptchaSiteKey' => SiteSettings::recaptchaSiteKey(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if (SiteSettings::recaptchaEnabled()) {
            $rules['g-recaptcha-response'] = ['required', new Recaptcha];
        }

        $request->validate($rules, [
            'g-recaptcha-response.required' => 'Please confirm you are not a robot.',
        ]);

        $this->guardAgainstAbusiveRegistration($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_canonical' => AccountSignals::canonicalEmail($request->email),
            'registration_ip' => $request->ip(),
            'device_hash' => AccountSignals::deviceHash($request->cookie(AccountSignals::DEVICE_COOKIE)),
        ]);

        // Give the new account its own workspace + Owner role.
        app(WorkspaceProvisioner::class)->createOwnerWorkspace($user);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Cheap anti-abuse signals (same process as hirin): disposable inboxes,
     * canonical-email aliases of existing accounts, and per-IP / per-device
     * registration caps. Error messages are deliberately vague so the limits
     * and signals are not disclosed.
     *
     * @throws ValidationException
     */
    private function guardAgainstAbusiveRegistration(Request $request): void
    {
        $email = (string) $request->input('email');

        if (AccountSignals::isDisposable($email)) {
            throw ValidationException::withMessages([
                'email' => 'This email domain is not supported. Please use a permanent email address.',
            ]);
        }

        $canonical = AccountSignals::canonicalEmail($email);
        if (User::where('email_canonical', $canonical)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email address cannot be used. Please try another.',
            ]);
        }

        $recentFromIp = User::where('registration_ip', $request->ip())
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentFromIp >= AccountSignals::MAX_PER_IP_PER_DAY) {
            throw ValidationException::withMessages([
                'email' => 'Registration is temporarily unavailable. Please try again later.',
            ]);
        }

        // Same browser registering again and again — regardless of network.
        $deviceHash = AccountSignals::deviceHash($request->cookie(AccountSignals::DEVICE_COOKIE));

        if ($deviceHash && User::where('device_hash', $deviceHash)->count() >= AccountSignals::MAX_PER_DEVICE) {
            throw ValidationException::withMessages([
                'email' => 'Registration is temporarily unavailable. Please try again later.',
            ]);
        }
    }
}
