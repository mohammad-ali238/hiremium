{{--
  Password input with a show/hide (eye) toggle on the right.
  Works on both bundles (guest app.js and dashboard livewire) — pure Alpine.

  variant="ui"     → styled like <x-ui.input> (dashboard / invite pages)
  variant="breeze" → styled like <x-text-input> (auth pages)

  All other attributes (name, id, wire:model, required, autocomplete…) pass through.
--}}
@props(['variant' => 'ui', 'error' => false])
@php
    $classes = $variant === 'breeze'
        ? 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm'
        : 'block w-full rounded-lg bg-surface text-app-text placeholder:text-muted text-sm h-10 px-3 border '
            .($error
                ? 'border-danger focus:border-danger focus:ring-danger/40'
                : 'border-line focus:border-primary focus:ring-brand');
@endphp
<div x-data="{ show: false }" class="relative">
    <input type="password" x-bind:type="show ? 'text' : 'password'"
           {{ $attributes->merge(['class' => $classes.' pr-10']) }}>
    <button type="button" x-on:click="show = ! show" tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
            :aria-label="show ? 'Hide password' : 'Show password'" :title="show ? 'Hide password' : 'Show password'">
        {{-- eye (click to show) --}}
        <svg x-show="! show" class="h-4.5 w-4.5" style="width:1.1rem;height:1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
        {{-- eye-off (click to hide) --}}
        <svg x-show="show" x-cloak style="width:1.1rem;height:1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
    </button>
</div>
