@props(['color' => 'gray', 'size' => 'md'])
@php
    $colors = [
        'gray'    => 'bg-muted/15 text-muted',
        'primary' => 'bg-primary-soft text-primary',
        'success' => 'bg-success/15 text-success',
        'warning' => 'bg-warning/15 text-warning',
        'danger'  => 'bg-danger/15 text-danger',
    ];
    $sizes = ['sm' => 'text-[11px] px-2 py-0.5', 'md' => 'text-xs px-2.5 py-1'];
@endphp
<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1 font-medium rounded-full '
        .($colors[$color] ?? $colors['gray']).' '.($sizes[$size] ?? $sizes['md']),
]) }}>{{ $slot }}</span>
