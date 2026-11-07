@props(['title' => null, 'subtitle' => null, 'padding' => true])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-card']) }}>
    @if($title || isset($actions))
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-line">
            <div class="min-w-0">
                @if($title)<h3 class="text-sm font-semibold text-app-text truncate">{{ $title }}</h3>@endif
                @if($subtitle)<p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="shrink-0">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class(['p-5' => $padding])>{{ $slot }}</div>
</div>
