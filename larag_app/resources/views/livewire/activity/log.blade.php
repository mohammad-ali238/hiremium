<div class="space-y-6">
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('app.dashboard')], ['label' => 'Activity Log']]" class="mb-2" />
        <h1 class="text-2xl font-bold text-app-text">Activity Log</h1>
        <p class="mt-1 text-sm text-muted">Everything that happens in your workspace, newest first.</p>
    </div>

    <x-ui.card :padding="false">
        @if ($activities->isEmpty())
            <x-ui.empty title="No activity yet" description="Actions like uploads, analysis and status changes will appear here." />
        @else
            <ul class="divide-y divide-line">
                @foreach ($activities as $activity)
                    <li class="px-5 py-4" wire:key="activity-{{ $activity->id }}">
                        <x-activity-item :activity="$activity" />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @if ($activities->hasPages())
        <div>{{ $activities->links() }}</div>
    @endif
</div>
