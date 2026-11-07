@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'for' => null,
    'required' => false,
])
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="block text-sm font-medium text-app-text">
            {{ $label }}@if($required)<span class="text-danger"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($error)
        <p class="text-xs text-danger">{{ $error }}</p>
    @elseif($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
