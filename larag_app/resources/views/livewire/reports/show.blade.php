@php
    $badge = ['emerald' => 'text-emerald-600 dark:text-emerald-400', 'indigo' => 'text-indigo-600 dark:text-indigo-400', 'amber' => 'text-amber-600 dark:text-amber-400', 'rose' => 'text-rose-600 dark:text-rose-400'];
    $maxMissing = collect($topMissing)->max() ?: 1;
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.breadcrumbs :items="[['label' => 'Reports', 'url' => route('app.reports')], ['label' => $job->title]]" class="mb-2" />
            <h1 class="text-2xl font-bold text-app-text">Screening report — {{ $job->title }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $analyzed }} candidate(s) analyzed · average score {{ $avg ?: '—' }}.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" size="sm" x-on:click="window.print()">Print / PDF</x-ui.button>
            @if ($analyzed > 0)
                <x-ui.button variant="secondary" size="sm" wire:click="export">Export CSV</x-ui.button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ($counts as $c)
            <div class="rounded-xl border border-line bg-card p-4 text-center">
                <p class="text-3xl font-bold tabular-nums {{ $badge[$c['color']] }}">{{ $c['count'] }}</p>
                <p class="text-xs text-muted">{{ $c['label'] }}</p>
            </div>
        @endforeach
    </div>

    <x-ui.card title="Top candidates" :padding="false">
        @if ($topCandidates->isEmpty())
            <x-ui.empty title="No scored candidates" description="Upload resumes to this job to build the report." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead><tr><x-ui.th>Candidate</x-ui.th><x-ui.th>Score</x-ui.th><x-ui.th>Status</x-ui.th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($topCandidates as $m)
                        <tr>
                            <x-ui.td>
                                <span class="font-medium text-app-text">{{ $m->candidate?->name ?? 'Unknown' }}</span>
                                <span class="text-xs text-muted"> · {{ $m->candidate?->email }}</span>
                            </x-ui.td>
                            <x-ui.td><x-ui.score-pill :score="$m->score" /></x-ui.td>
                            <x-ui.td class="text-muted">{{ ucfirst($m->status) }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if (! empty($topMissing))
        <x-ui.card title="Skill gaps">
            <div class="space-y-3">
                @foreach ($topMissing as $skill => $count)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm"><span class="text-app-text">{{ $skill }}</span><span class="tabular-nums text-muted">{{ $count }}</span></div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-line/60"><div class="h-full rounded-full bg-amber-500" style="width: {{ round($count / $maxMissing * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</div>
