<x-guest-layout>
    <x-slot name="title">{{ __('Check your inbox') }}</x-slot>
    <x-slot name="subtitle">{{ __('One last step — confirm your email address and your account is ready to go.') }}</x-slot>

    <div class="flex justify-center mb-6">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
        </span>
    </div>

    <p class="text-sm text-slate-600 leading-relaxed text-center">
        {{ __("We've sent a verification link to your email address. Click it and you'll be taken straight to your dashboard. Can't find it? Check your spam folder — or request a fresh one below.") }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-medium text-emerald-700 text-center">
            {{ __('A new verification link is on its way to your inbox.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button type="submit"
                class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
            {{ __('Resend verification email') }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-slate-500 hover:text-slate-800 underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
            {{ __('Log out') }}
        </button>
    </form>
</x-guest-layout>
