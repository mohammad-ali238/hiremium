@props(['confidence' => null, 'showLabel' => true, 'size' => 'md'])
{{-- How certain the parser is that it read the CV correctly, always shown as a
     percentage. Bands follow the minimum in config/hiremium.php: anything below
     it was never scored, so it reads as a warning rather than a statistic.
     No confidence reported at all means nothing is rendered: an icon with an
     empty value next to it reads as a broken statistic, not as "unknown". --}}
@php
    $cls = \App\Services\ResumeParsing\ParseConfidence::class;
    $percent = $cls::percent($confidence);

    if ($percent === null) {
        return;
    }

    $label = $cls::label($confidence);
    $min = $cls::percent($cls::minimum());

    $c = match (true) {
        $percent >= 90 => 'emerald',
        $percent >= $min => 'indigo',
        $percent >= 50 => 'amber',
        default => 'rose',
    };

    $map = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
        'indigo'  => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/25',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/25',
    ];

    $pad = $size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs';
    $title = "Parsing confidence: the parser's own certainty that it read this resume correctly.";
@endphp
<span title="{{ $title }}" {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full font-semibold ring-1 ring-inset '.$pad.' '.$map[$c],
]) }}>
    <svg class="h-3 w-3 shrink-0 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3 4 6v6c0 4.5 3.2 8.3 8 9 4.8-.7 8-4.5 8-9V6l-8-3z"/>
    </svg>
    <span class="tabular-nums tracking-tight">{{ $percent.'%' }}</span>
    @if ($showLabel)<span class="opacity-50">·</span><span class="font-medium">{{ $label }}</span>@endif
</span>
