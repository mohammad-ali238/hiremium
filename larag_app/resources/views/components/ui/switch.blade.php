@props(['label' => null])
{{-- Visually a toggle; underneath a real checkbox so it works with forms and
     wire:model via passed-through attributes. --}}
<label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
    <span class="relative inline-block">
        <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="block h-6 w-10 rounded-full bg-line peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-brand transition"></span>
        <span class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-4"></span>
    </span>
    @if($label)<span class="text-sm text-app-text">{{ $label }}</span>@endif
</label>
