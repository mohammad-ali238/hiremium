{{-- Presentational table shell. Compose with <x-ui.th> / <x-ui.td>:
     <x-ui.table><thead><tr><x-ui.th>…</x-ui.th></tr></thead>
       <tbody class="divide-y divide-line">…<x-ui.td>…</x-ui.td>…</tbody></x-ui.table> --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-line']) }}>
    <table class="min-w-full divide-y divide-line text-sm">{{ $slot }}</table>
</div>
