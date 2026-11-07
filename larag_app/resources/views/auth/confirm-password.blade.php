<x-guest-layout>
    <x-slot name="title">{{ __('Confirm your password') }}</x-slot>
    <x-slot name="subtitle">{{ __("You're entering a secure area. Please re-enter your password to continue.") }}</x-slot>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-ui.password variant="breeze" id="password" class="block mt-1 w-full"
                            name="password"
                            required autocomplete="current-password" autofocus />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <button type="submit"
                class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
            {{ __('Confirm and continue') }}
        </button>
    </form>
</x-guest-layout>
