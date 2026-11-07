@props(['activity'])
<div class="flex items-start gap-3">
    <x-ui.avatar :name="$activity->user?->name ?? 'System'" size="sm" />
    <div class="min-w-0 flex-1">
        <p class="text-sm text-app-text">{{ $activity->description ?: $activity->action }}</p>
        <p class="text-xs text-muted">
            {{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at?->diffForHumans() }}
        </p>
    </div>
</div>
