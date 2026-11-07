@props(['name' => '', 'src' => null, 'size' => 'md'])
@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-12 w-12 text-base'];
    $cls = $sizes[$size] ?? $sizes['md'];
    $initial = strtoupper(mb_substr(trim($name) !== '' ? $name : '?', 0, 1));
@endphp
@if($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'rounded-full object-cover '.$cls]) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-grid place-items-center rounded-full bg-primary text-primary-fg font-semibold '.$cls]) }}>{{ $initial }}</span>
@endif
