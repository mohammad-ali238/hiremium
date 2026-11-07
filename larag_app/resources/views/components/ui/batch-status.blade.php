{{-- Single source of truth for batch status badges (colour + label). --}}
@props(['status', 'size' => 'md'])
@php
    [$color, $label] = match ($status) {
        'completed'       => ['success', 'Completed'],
        'partial'         => ['warning', 'Partial'],
        'failed'          => ['danger',  'Failed'],
        'processing'      => ['primary', 'Processing'],
        'extracting', 'extracting_claimed' => ['primary', 'Preparing'],
        'waiting_credits' => ['warning', 'Waiting on AI usage'],
        default           => ['gray', ucfirst((string) $status)],
    };
@endphp
<x-ui.badge :color="$color" :size="$size" {{ $attributes }}>{{ $label }}</x-ui.badge>
