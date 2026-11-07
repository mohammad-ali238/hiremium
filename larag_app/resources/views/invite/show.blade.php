<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold text-app-text">You're invited</h1>
        <p class="mt-1 text-sm text-muted">
            Join <strong>{{ $invite->workspace->name }}</strong> as {{ $invite->role }}. Create your account to continue.
        </p>
    </div>

    <form method="POST" action="{{ route('invite.accept', $invite->token) }}" class="space-y-4">
        @csrf

        <x-ui.field label="Email">
            <x-ui.input type="email" value="{{ $invite->email }}" disabled />
        </x-ui.field>

        <x-ui.field label="Your name" :error="$errors->first('name')">
            <x-ui.input name="name" value="{{ old('name') }}" required autofocus :error="$errors->has('name')" />
        </x-ui.field>

        <x-ui.field label="Password" :error="$errors->first('password')">
            <x-ui.password name="password" required :error="$errors->has('password')" />
        </x-ui.field>

        <x-ui.field label="Confirm password">
            <x-ui.password name="password_confirmation" required />
        </x-ui.field>

        <x-ui.button type="submit" class="w-full">Accept invite</x-ui.button>
    </form>
</x-guest-layout>
