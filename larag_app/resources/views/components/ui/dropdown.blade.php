@props(['align' => 'right', 'width' => 'w-52'])
{{-- The menu stays inside the component's DOM (so wire:* directives in it work normally) but is
     positioned `fixed` next to its trigger: fixed elements are laid out against the viewport, so
     an overflow container (e.g. the scrollable table shell) can neither clip the menu nor grow a
     scrollbar because of it. Repositions on scroll/resize and flips upward when out of room. --}}
<div x-data="{
        open: false,
        pos: { top: 0, left: 0 },
        toggle() { this.open ? this.close() : this.show(); },
        close() { this.open = false; },
        show() {
            this.open = true;
            this.$nextTick(() => this.place());
        },
        place() {
            const trigger = this.$refs.trigger;
            const menu = this.$refs.menu;
            if (! trigger || ! menu) return;
            const r = trigger.getBoundingClientRect();
            const menuW = menu.offsetWidth || 208;
            const menuH = menu.offsetHeight || 0;
            let left = '{{ $align }}' === 'left' ? r.left : r.right - menuW;
            left = Math.max(8, Math.min(left, window.innerWidth - menuW - 8));
            let top = r.bottom + 6;
            if (menuH && top + menuH > window.innerHeight - 8 && r.top - menuH - 6 > 8) {
                top = r.top - menuH - 6;
            }
            this.pos = { top, left };
        }
     }"
     @keydown.escape.window="close()"
     @scroll.window.passive="open && place()"
     @resize.window.passive="open && place()"
     {{ $attributes->merge(['class' => 'relative inline-block']) }}>
    <div x-ref="trigger" @click="toggle()">{{ $trigger }}</div>
    <div x-show="open" x-cloak x-ref="menu" @click.outside="close()" @click="close()" x-transition.opacity
         :style="`position: fixed; top: ${pos.top}px; left: ${pos.left}px;`"
         class="z-50 {{ $width }} rounded-lg border border-line bg-card shadow-lg py-1">
        {{ $slot }}
    </div>
</div>
