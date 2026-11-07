@props([
    'score' => null,        // int|null — drives which list leads
    'strengths' => [],      // reasons to hire
    'concerns' => [],       // reasons to reject / risks
    'compact' => false,     // tighter layout for cards/compare columns
])
@php
    // Adaptive emphasis (never suppression):
    //   >= 80  → strengths lead, concerns de-emphasised as "Watch-outs"
    //   <  40  → concerns lead, strengths de-emphasised as "Redeeming points"
    //   else   → both lists side-by-side, equal weight
    $strengths = array_values(array_filter((array) $strengths));
    $concerns  = array_values(array_filter((array) $concerns));
    $s = $score === null ? null : (int) $score;
    $mode = $s === null ? 'balanced' : ($s >= 80 ? 'hire' : ($s < 40 ? 'reject' : 'balanced'));

    $hire = ['title' => 'Reasons to hire', 'items' => $strengths, 'color' => 'success', 'mark' => '✓'];
    $rej  = ['title' => 'Reasons to reject', 'items' => $concerns,  'color' => 'danger',  'mark' => '✕'];
    if ($mode === 'hire')   { $rej['title']  = 'Watch-outs'; }
    if ($mode === 'reject') { $hire['title'] = 'Redeeming points'; }

    $primary   = $mode === 'reject' ? $rej  : $hire;
    $secondary = $mode === 'reject' ? $hire : $rej;
    $textSize  = $compact ? 'text-xs' : 'text-sm';
@endphp

@if ($strengths || $concerns)
    <div {{ $attributes->class(['grid gap-4', 'sm:grid-cols-2' => $mode === 'balanced' && $strengths && $concerns]) }}>
        {{-- Primary list --}}
        @if ($primary['items'])
            <div class="rounded-lg border p-3.5 {{ $primary['color'] === 'success' ? 'border-success/30 bg-success/5' : 'border-danger/30 bg-danger/5' }}">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider {{ $primary['color'] === 'success' ? 'text-success' : 'text-danger' }}">{{ $primary['title'] }}</p>
                <ul class="space-y-1.5">
                    @foreach ($primary['items'] as $item)
                        <li class="flex gap-2 {{ $textSize }} text-app-text"><span class="shrink-0 font-bold {{ $primary['color'] === 'success' ? 'text-success' : 'text-danger' }}">{{ $primary['mark'] }}</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Secondary list: full weight when balanced, de-emphasised otherwise --}}
        @if ($secondary['items'])
            @if ($mode === 'balanced')
                <div class="rounded-lg border p-3.5 {{ $secondary['color'] === 'success' ? 'border-success/30 bg-success/5' : 'border-danger/30 bg-danger/5' }}">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider {{ $secondary['color'] === 'success' ? 'text-success' : 'text-danger' }}">{{ $secondary['title'] }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($secondary['items'] as $item)
                            <li class="flex gap-2 {{ $textSize }} text-app-text"><span class="shrink-0 font-bold {{ $secondary['color'] === 'success' ? 'text-success' : 'text-danger' }}">{{ $secondary['mark'] }}</span><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="rounded-lg border border-line px-3.5 py-2.5">
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $secondary['title'] }}</p>
                    <ul class="space-y-1">
                        @foreach ($secondary['items'] as $item)
                            <li class="flex gap-2 text-xs text-muted"><span class="shrink-0">{{ $secondary['mark'] }}</span><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>
@endif
