@props(['title' => 'This record was deleted', 'description' => null, 'label' => null, 'deletedAt' => null])
{{-- Friendly in-dashboard page for links (notifications, bookmarks) that point at a record the
     user has since deleted — instead of a bare 404. --}}
<div {{ $attributes->merge(['class' => 'mx-auto max-w-xl']) }}>
    <div class="rounded-2xl border border-line bg-card px-6 py-12 text-center shadow-sm">
        <span class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-2xl bg-danger/10 text-danger">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                <path d="M10 11v6M14 11v6"/>
            </svg>
        </span>
        <h1 class="text-xl font-bold text-app-text">{{ $title }}</h1>
        @if ($description)
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ $description }}</p>
        @endif
        @if ($label || $deletedAt)
            <dl class="mx-auto mt-6 inline-grid gap-x-8 gap-y-1 rounded-xl bg-app-bg/60 px-5 py-3 text-left text-sm sm:grid-cols-2">
                @if ($label)<div><dt class="text-xs uppercase tracking-wide text-muted">Record</dt><dd class="font-medium text-app-text">{{ $label }}</dd></div>@endif
                @if ($deletedAt)<div><dt class="text-xs uppercase tracking-wide text-muted">Deleted</dt><dd class="font-medium text-app-text">{{ \Illuminate\Support\Carbon::parse($deletedAt)->format('M j, Y H:i') }}</dd></div>@endif
            </dl>
        @endif
        <p class="mt-4 text-xs text-muted">The AI usage spent on it is still listed on your <a href="{{ route('app.usage') }}" class="text-primary hover:underline" wire:navigate>AI usage</a> page.</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    </div>
</div>
