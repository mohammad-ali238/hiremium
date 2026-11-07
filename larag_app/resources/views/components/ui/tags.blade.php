@props(['model', 'placeholder' => 'Type a skill and press Enter…'])
{{-- Tag input bound to a Livewire array property via entangle. Usage:
     <x-ui.tags model="mustHave" /> --}}
<div
    x-data="{
        tags: $wire.entangle('{{ $model }}'),
        input: '',
        add() {
            const v = this.input.replace(/,+$/, '').trim();
            if (v && ! this.tags.includes(v)) { this.tags = [...this.tags, v]; }
            this.input = '';
        },
        remove(i) { this.tags = this.tags.filter((_, idx) => idx !== i); }
    }"
    class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface p-2 focus-within:border-primary focus-within:ring-1 focus-within:ring-brand">
    <template x-for="(tag, i) in tags" :key="i">
        <span class="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary">
            <span x-text="tag"></span>
            <button type="button" @click="remove(i)" class="text-primary/70 hover:text-primary" aria-label="Remove">×</button>
        </span>
    </template>
    <input x-model="input"
           @keydown.enter.prevent="add()" @keydown.comma.prevent="add()" @blur="add()"
           placeholder="{{ $placeholder }}"
           class="min-w-[8rem] flex-1 border-0 bg-transparent p-0 text-sm text-app-text placeholder:text-muted focus:ring-0">
</div>
