@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'loading' => false,
])
@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-bg disabled:opacity-60 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'text-xs h-8 px-3',
        'md' => 'text-sm h-10 px-4',
        'lg' => 'text-sm h-11 px-5',
    ];
    $variants = [
        'primary'   => 'bg-primary text-primary-fg hover:opacity-90 shadow-sm',
        'secondary' => 'bg-surface text-app-text border border-line hover:bg-primary-soft/60',
        'ghost'     => 'text-muted hover:bg-primary-soft/60 hover:text-app-text',
        'danger'    => 'bg-danger text-white hover:opacity-90 shadow-sm',
        'subtle'    => 'bg-primary-soft text-primary hover:bg-primary-soft/80',
    ];
    $cls = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
    $tag = $href ? 'a' : 'button';
@endphp
<{{ $tag }}
    @if($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $cls]) }}
    @if($loading) aria-busy="true" @endif
>
    @if($loading)
        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4z"/>
        </svg>
    @endif
    {{ $slot }}
</{{ $tag }}>
