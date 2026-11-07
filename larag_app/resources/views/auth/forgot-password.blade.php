<x-guest-layout>
    <x-slot name="title">{{ __('Reset your password') }}</x-slot>
    <x-slot name="subtitle">{{ __("Enter the email you signed up with and we'll send you a secure link to choose a new password.") }}</x-slot>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="you@company.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <button type="submit"
                class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
            {{ __('Email me a reset link') }}
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        {{ __('Remembered it after all?') }}
        <a class="font-medium text-indigo-600 hover:text-indigo-800 underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
            {{ __('Back to login') }}
        </a>
    </p>
</x-guest-layout>
