@props(['score' => 0, 'showLabel' => true])
{{-- Signature element: match score in tight tabular figures + color-coded band.
     Bands come from config/hiremium.php (admin-configurable). --}}
@php
    $s = (int) $score;
    $bands = config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);
    if ($s >= $bands['excellent']) { $label = 'Excellent'; $c = 'emerald'; }
    elseif ($s >= $bands['good'])  { $label = 'Good';      $c = 'indigo'; }
    elseif ($s >= $bands['fair'])  { $label = 'Fair';      $c = 'amber'; }
    else                           { $label = 'Low fit';   $c = 'rose'; }

    $map = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
        'indigo'  => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/25',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/25',
    ];
@endphp
<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$map[$c],
]) }}>
    <span class="tabular-nums tracking-tight">{{ $s }}</span>
    @if($showLabel)<span class="opacity-50">·</span><span>{{ $label }}</span>@endif
</span>
