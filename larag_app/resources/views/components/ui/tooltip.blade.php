@props(['text' => ''])
<span x-data="{ show: false }" class="relative inline-flex"
      @mouseenter="show = true" @mouseleave="show = false"
      @focusin="show = true" @focusout="show = false">
    {{ $slot }}
    <span x-show="show" x-cloak x-transition
          class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-40 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg dark:bg-slate-700">
        {{ $text }}
    </span>
</span>
