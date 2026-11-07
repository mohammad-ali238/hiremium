{{-- Global confirm dialog. Mount ONCE in the layout. Trigger from anywhere:
     @click="$store.confirm.ask({ title, text, danger: true }).then(ok => ok && $wire.delete(id))" --}}
<div x-data x-cloak x-show="$store.confirm.open" class="fixed inset-0 z-[60] overflow-y-auto" style="display:none">
    <div x-show="$store.confirm.open" x-transition.opacity class="fixed inset-0 bg-slate-900/50" @click="$store.confirm.cancel()"></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div x-show="$store.confirm.open" x-transition
             class="relative w-full max-w-sm rounded-xl border border-line bg-card p-6 text-center shadow-xl">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full"
                 :class="$store.confirm.danger ? 'bg-danger/10 text-danger' : 'bg-primary-soft text-primary'">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.3 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.3a2 2 0 0 0-3.4 0z"/></svg>
            </div>
            <h3 class="mt-4 text-base font-semibold text-app-text" x-text="$store.confirm.title"></h3>
            <p class="mt-1.5 text-sm text-muted" x-text="$store.confirm.text"></p>
            <div class="mt-6 flex gap-2">
                <button @click="$store.confirm.cancel()"
                        class="flex-1 h-10 rounded-lg border border-line bg-surface text-sm font-medium text-app-text hover:bg-primary-soft/60"
                        x-text="$store.confirm.cancelText"></button>
                <button @click="$store.confirm.confirm()"
                        class="flex-1 h-10 rounded-lg text-sm font-medium text-white"
                        :class="$store.confirm.danger ? 'bg-danger hover:opacity-90' : 'bg-primary hover:opacity-90'"
                        x-text="$store.confirm.confirmText"></button>
            </div>
        </div>
    </div>
</div>
