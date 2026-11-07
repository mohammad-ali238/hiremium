{{-- Per-must-have verdicts from the AI, reconciled in code against the job's list.
     Renders only when the job had must-haves (empty array → nothing). --}}
@props(['coverage' => [], 'compact' => false])
@php
    $rows = array_values(array_filter((array) $coverage, fn ($r) => is_array($r) && ($r['skill'] ?? '') !== ''));
    $met = count(array_filter($rows, fn ($r) => ! empty($r['met'])));
    $total = count($rows);
@endphp
@if ($total)
    <div {{ $attributes }}>
        <p class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
            Must-have skills
            <x-ui.badge :color="$met === $total ? 'success' : ($met === 0 ? 'danger' : 'warning')" size="sm">{{ $met }} / {{ $total }} met</x-ui.badge>
        </p>
        <ul class="divide-y divide-line rounded-lg border border-line">
            @foreach ($rows as $r)
                <li class="flex items-start gap-2.5 px-3 {{ $compact ? 'py-1.5' : 'py-2' }}">
                    @if (! empty($r['met']))
                        <span class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-success/15 text-[11px] font-bold text-success" aria-label="Met">✓</span>
                    @else
                        <span class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-danger/15 text-[11px] font-bold text-danger" aria-label="Not met">✕</span>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-medium {{ ! empty($r['met']) ? 'text-app-text' : 'text-danger' }}">{{ $r['skill'] }}</p>
                        @if (! empty($r['evidence']))<p class="text-xs text-muted">{{ $r['evidence'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
