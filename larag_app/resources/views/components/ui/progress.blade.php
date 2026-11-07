@props(['value' => 0, 'color' => 'primary', 'label' => null, 'showValue' => false])
@php
    $v = max(0, min(100, (int) $value));
    $c = ['primary' => 'bg-primary', 'success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger'][$color] ?? 'bg-primary';
@endphp
<div {{ $attributes }}>
    @if($label || $showValue)
        <div class="mb-1 flex items-center justify-between text-xs text-muted">
            <span>{{ $label }}</span>
            @if($showValue)<span class="tabular-nums font-medium text-app-text">{{ $v }}%</span>@endif
        </div>
    @endif
    <div class="h-2 w-full overflow-hidden rounded-full bg-line/70">
        <div class="h-full rounded-full {{ $c }} transition-all duration-500" style="width: {{ $v }}%"></div>
    </div>
</div>
