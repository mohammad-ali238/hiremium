<x-guest-layout>
    <div class="text-center">
        <h1 class="text-xl font-bold text-app-text">Invite unavailable</h1>
        <p class="mt-2 text-sm text-muted">
            {{ $reason ?? 'This invitation link is invalid, has expired, or was already used.' }}
        </p>
        <a href="{{ route('home') }}" class="mt-6 inline-block text-sm font-medium text-primary hover:underline">Back to home</a>
    </div>
</x-guest-layout>
