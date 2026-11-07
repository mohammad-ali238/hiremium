@props(['tabs' => [], 'default' => null])
{{-- Alpine tab group. Put panels in the slot keyed by the tab id:
     <x-ui.tabs :tabs="['overview'=>'Overview','skills'=>'Skills']">
        <div x-show="tab==='overview'">…</div>
        <div x-show="tab==='skills'" x-cloak>…</div>
     </x-ui.tabs> --}}
@php $default = $default ?? array_key_first($tabs); @endphp
<div x-data="{ tab: @js($default) }" {{ $attributes }}>
    <div class="flex items-center gap-1 border-b border-line overflow-x-auto">
        @foreach($tabs as $key => $label)
            <button type="button" @click="tab = @js($key)"
                    :class="tab === @js($key) ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-app-text'"
                    class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition">{{ $label }}</button>
        @endforeach
    </div>
    <div class="pt-4">{{ $slot }}</div>
</div>
