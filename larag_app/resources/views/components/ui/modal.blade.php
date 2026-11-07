@props(['name', 'title' => null, 'maxWidth' => 'lg'])
{{-- Open/close via events (name-matched):
     JS:       window.dispatchEvent(new CustomEvent('open-modal', { detail: 'name' }))
     Livewire: $this->dispatch('open-modal', 'name')  /  'close-modal' --}}
@php
    $mw = ['sm'=>'max-w-sm','md'=>'max-w-md','lg'=>'max-w-lg','xl'=>'max-w-xl','2xl'=>'max-w-2xl'][$maxWidth] ?? 'max-w-lg';
@endphp
{{-- `detail` is a string when fired from JS, but Livewire's $this->dispatch('open-modal', 'name')
     delivers it as an array (["name"]) — accept both. --}}
<div
    x-data="{ open: false, matches(d) { return d === '{{ $name }}' || (Array.isArray(d) && d[0] === '{{ $name }}') || (d && d.name === '{{ $name }}'); } }"
    x-on:open-modal.window="matches($event.detail) && (open = true)"
    x-on:close-modal.window="matches($event.detail) && (open = false)"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50" @click="open = false"></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition
             class="relative w-full {{ $mw }} rounded-xl border border-line bg-card shadow-xl">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-line">
                <h3 class="text-sm font-semibold text-app-text">{{ $title }}</h3>
                <button type="button" @click="open = false" class="text-muted hover:text-app-text" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5">{{ $slot }}</div>
            @isset($footer)
                <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-line">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
