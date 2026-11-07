@props(['href' => null, 'danger' => false])
@php
    $cls = 'flex w-full items-center gap-2 px-4 py-2 text-sm text-left transition '
        .($danger ? 'text-danger hover:bg-danger/10' : 'text-muted hover:bg-primary-soft/60 hover:text-app-text');
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
