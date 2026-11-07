@props(['label' => null])
<label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
    <input type="checkbox" {{ $attributes->merge([
        'class' => 'h-4 w-4 rounded border-line text-primary focus:ring-brand bg-surface',
    ]) }}>
    @if($label)<span class="text-sm text-app-text">{{ $label }}</span>@endif
</label>
