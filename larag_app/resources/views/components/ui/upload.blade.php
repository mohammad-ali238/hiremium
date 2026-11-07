@props([
    'multiple' => true,
    'accept' => '.pdf,.docx,.doc,.txt,.zip',
    'hint' => 'PDF, DOCX, DOC, TXT or ZIP · up to 25 files',
    'id' => 'file-upload',
])
{{-- Presentational dropzone. Wire the <input> to Livewire in Phase 5 via
     passed-through attributes (e.g. wire:model="files"). --}}
<div x-data="{ over: false }"
     @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false"
     :class="over ? 'border-primary bg-primary-soft/40' : 'border-line'"
     {{ $attributes->merge(['class' => 'relative rounded-xl border-2 border-dashed bg-surface p-8 text-center transition']) }}>
    <input type="file" id="{{ $id }}" @if($multiple) multiple @endif accept="{{ $accept }}"
           class="absolute inset-0 cursor-pointer opacity-0" aria-label="Upload files">
    <div class="pointer-events-none">
        <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 9 5-5 5 5"/><path d="M12 4v12"/></svg>
        </span>
        <p class="text-sm font-medium text-app-text">Drag &amp; drop CVs here, or <span class="text-primary">browse</span></p>
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    </div>
</div>
