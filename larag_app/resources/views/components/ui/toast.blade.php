{{-- Global toast stack. Mount ONCE in the layout. Trigger from anywhere:
     JS:       window.dispatchEvent(new CustomEvent('toast', { detail: { type:'success', message:'Saved' } }))
     Livewire: $this->dispatch('toast', type: 'success', message: 'Saved'); --}}
<div x-data x-on:toast.window="$store.toast.push($event.detail)" x-cloak
     class="fixed bottom-4 right-4 z-[70] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition
             class="flex items-start gap-3 rounded-lg border border-line bg-card p-3.5 shadow-lg"
             :class="{
                'text-success': t.type === 'success',
                'text-danger':  t.type === 'error',
                'text-warning': t.type === 'warning',
                'text-primary': t.type === 'info' || ! t.type,
             }">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                {{-- x-show, not x-if: <template> has no .content inside an <svg> namespace, so x-if throws
                     "Cannot read properties of undefined (reading 'cloneNode')" the first time a toast renders. --}}
                <path x-show="t.type === 'success'" d="M20 6 9 17l-5-5"/>
                <path x-show="t.type === 'error'" d="M18 6 6 18M6 6l12 12"/>
                <path x-show="t.type === 'warning' || t.type === 'info' || ! t.type" d="M12 8v5M12 17h.01M10.3 3.3 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.3a2 2 0 0 0-3.4 0z"/>
            </svg>
            <p class="flex-1 text-sm text-app-text" x-text="t.message"></p>
            <button @click="$store.toast.remove(t.id)" class="text-muted hover:text-app-text" aria-label="Dismiss">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>
