{{--
  Restricted rich-text editor (Quill 2) for job descriptions.
  Allowed: bold, italic, heading (h2/h3), bullet + numbered lists, quote.
  Not allowed (cannot even be created): links, images, files, colours, code…
  The server re-sanitizes with App\Support\RichText on save regardless.

  Binding strategy (important): Livewire's DOM morph clones nodes and drops
  JS expando props — which silently destroys Quill's DOM→blot links (the
  "Cannot read properties of null (reading 'offset')" crash on focus). So the
  ENTIRE Alpine/Quill widget lives under one wire:ignore root that Livewire
  never touches, and the value travels through a plain hidden <input
  wire:model> that sits OUTSIDE it as a sibling. Alpine writes editor HTML
  into that input and fires `input`, which is exactly what wire:model hears.

  Usage: <x-ui.rich-editor id="description" wire:model="description" :min="50" :max="8000" />
--}}
@props(['id', 'min' => 0, 'max' => null, 'placeholder' => ''])

<div class="rich-editor">
    {{-- Livewire-owned carrier (outside the ignored widget). --}}
    <input type="hidden" id="{{ $id }}-carrier" {{ $attributes->whereStartsWith('wire:model') }}>

    {{-- Livewire-ignored widget: Alpine + Quill own this subtree exclusively. --}}
    <div
        wire:ignore
        x-data="hiremiumRichEditor({ editor: '{{ $id }}', carrier: '{{ $id }}-carrier', model: @js($attributes->wire('model')->value()), min: {{ (int) $min }}, max: {{ $max === null ? 'null' : (int) $max }} })"
    >
        <div id="{{ $id }}" data-placeholder="{{ $placeholder }}"></div>

        {{-- Live character counter --}}
        <div class="mt-1.5 flex items-center justify-between gap-3 text-xs">
            <span class="text-muted">Bold, italic, headings, lists and quotes. Links and images are not allowed.</span>
            <span :class="{ 'text-muted': status === 'ok' || status === 'empty', 'text-warning': status === 'short', 'text-danger': status === 'long' }" class="shrink-0 tabular-nums">
                <span x-text="count.toLocaleString()"></span><span x-show="max" x-cloak> / <span x-text="(max || 0).toLocaleString()"></span></span>
                <span x-show="status === 'short'" x-cloak> · at least <span x-text="min"></span> characters</span>
                <span x-show="status === 'long'" x-cloak> · too long</span>
            </span>
        </div>
    </div>
</div>
