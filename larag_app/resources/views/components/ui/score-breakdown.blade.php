{{-- How the match score was reached: one bar per rubric dimension, with the
     points it earned out of its quota, plus a career-gap note when the CV dates
     showed one.

     Matches analysed before rubric scoring have no breakdown — they fall back to
     the three AI sub-scores they were saved with, exactly as they always showed. --}}
@props(['subScores' => [], 'breakdown' => [], 'compact' => false])
@php
    $rows = [];

    foreach ((array) ($breakdown['dimensions'] ?? []) as $key => $d) {
        $rows[] = [
            'label' => $d['label'] ?? $key,
            'percent' => (int) ($d['percent'] ?? 0),
            'quota' => (int) ($d['quota'] ?? 0),
            'points' => $d['points'] ?? null,
        ];
    }

    if (! $rows) {
        foreach (['skills' => 'Skills', 'experience' => 'Experience', 'education' => 'Education'] as $key => $label) {
            if (isset($subScores[$key])) {
                $rows[] = ['label' => $label, 'percent' => (int) $subScores[$key], 'quota' => null, 'points' => null];
            }
        }
    }

    $hasGap = ! empty(($breakdown['career_gap'] ?? [])['has_gap']);
@endphp
@if ($rows || $hasGap)
    <div {{ $attributes }}>
        @if ($rows)
            <div class="grid {{ $compact ? 'gap-3' : 'gap-4' }} sm:grid-cols-3">
                @foreach ($rows as $r)
                    <div>
                        <x-ui.progress :value="$r['percent']" :label="$r['label']" :show-value="true" />
                        @if ($r['quota'])
                            <p class="mt-1 text-[11px] text-muted">{{ rtrim(rtrim(number_format((float) $r['points'], 1), '0'), '.') }} of {{ $r['quota'] }} pts</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($hasGap)
            <p class="{{ $rows ? 'mt-4 ' : '' }}flex items-start gap-2 text-xs text-warning">
                <svg class="mt-px h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                </svg>
                <span>This candidate has 1 or more career gaps in their job experience.</span>
            </p>
        @endif
    </div>
@endif
