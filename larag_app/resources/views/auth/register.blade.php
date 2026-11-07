<x-guest-layout>
    <x-slot name="title">{{ __('Create your account') }}</x-slot>
    <x-slot name="subtitle">{{ __('Start scoring applications and parsing resumes in minutes.') }}</x-slot>

    @php
        $socialProviders = \App\Support\SiteSettings::enabledSocialProviders();
        $showEmailForm = $errors->any() || old('name') || old('email') || empty($socialProviders);
    @endphp

    <div x-data="{ emailForm: @js((bool) $showEmailForm) }">

        @if (! empty($socialProviders))
            {{-- Step 1: social sign-up options --}}
            <div x-show="! emailForm" x-cloak>
                <p class="text-sm font-medium text-slate-700 mb-4">{{ __('Sign up with one click') }}</p>

                @include('auth.partials.social-buttons', ['divider' => false])

                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-xs">
                        <span class="bg-white px-3 text-gray-500 uppercase tracking-wide">{{ __('or') }}</span>
                    </div>
                </div>

                <button type="button" @click="emailForm = true"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    {{ __('Continue with email') }}
                </button>
            </div>
        @endif

        {{-- Step 2: email registration form --}}
        <div x-show="emailForm" @if (! empty($socialProviders)) x-cloak @endif>
            @if (! empty($socialProviders))
                <button type="button" @click="emailForm = false"
                        class="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    {{ __('Back to quick sign-up options') }}
                </button>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Full name')" />
                    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Jane Doe" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('Work email')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@company.com" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-ui.password variant="breeze" id="password" class="block mt-1 w-full" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                    <x-ui.password variant="breeze" id="password_confirmation" class="block mt-1 w-full" name="password_confirmation" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                @if (! empty($recaptchaEnabled))
                    <div>
                        <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                        <x-input-error :messages="$errors->get('g-recaptcha-response')" class="mt-2" />
                    </div>
                @endif

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    {{ __('Create account') }}
                </button>
            </form>
        </div>

        <p class="mt-8 text-center text-sm text-slate-600">
            {{ __('Already have an account?') }}
            <a class="font-medium text-indigo-600 hover:text-indigo-800 underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Log in') }}
            </a>
        </p>
    </div>

    @if (! empty($recaptchaEnabled))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</x-guest-layout>
