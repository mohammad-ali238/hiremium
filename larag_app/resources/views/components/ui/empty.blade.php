@props(['title' => 'Nothing here yet', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-14 px-6']) }}>
    <span class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.5A2 2 0 0 0 16.8 5H7.2a2 2 0 0 0-1.7 1.5z"/>
        </svg>
    </span>
    <h3 class="text-sm font-semibold text-app-text">{{ $title }}</h3>
    @if($description)<p class="mt-1 max-w-sm text-sm text-muted">{{ $description }}</p>@endif
    @isset($action)<div class="mt-5">{{ $action }}</div>@endisset
</div>
