{{-- Responsive filter bar.
     Desktop: one row — a wide search + the filter selects filling the rest.
     Mobile: search only; a centered "More filters" button slide-toggles the
     rest, one field per row.
     Usage:
       <x-ui.filter-bar>
         <x-slot:search> …search input… </x-slot:search>
         <x-ui.select …/> <x-ui.select …/>
       </x-ui.filter-bar>
--}}
<div x-data="{ open: false }" {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        @isset($search)
            <div class="w-full sm:flex-[2] sm:min-w-[14rem]">{{ $search }}</div>
        @endisset

        {{-- Filter group: hidden on mobile until toggled; on desktop it becomes
             `display:contents` so the selects flow into the row and share the
             remaining width (each flex-1). --}}
        <div class="flex-col gap-3 sm:contents [&>*]:w-full sm:[&>*]:w-auto sm:[&>*]:flex-1 sm:[&>*]:min-w-[8rem]"
             :class="open ? 'flex' : 'hidden'">
            {{ $slot }}
        </div>
    </div>

    {{-- Mobile-only toggle --}}
    <div class="text-center sm:hidden">
        <button type="button" @click="open = ! open"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
            <span x-text="open ? 'Fewer filters' : 'More filters'"></span>
            <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
    </div>
</div>
