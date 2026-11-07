@php
    // Literal classes so Tailwind's content scan compiles them.
    $barColors = ['emerald' => 'bg-emerald-500', 'indigo' => 'bg-indigo-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500'];
    $maxMissing = collect($topMissing)->max('count') ?: 1;
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-app-text">Screening Insights</h1>
            <p class="mt-1 text-sm text-muted">How your candidate pool scores, and where the skill gaps are.</p>
        </div>
        <x-ui.select wire:model.live="jobFilter" class="w-52">
            <option value="">All jobs</option>
            @foreach ($jobs as $job)<option value="{{ $job->id }}">{{ $job->title }}</option>@endforeach
        </x-ui.select>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Candidates analyzed">
            <p class="text-4xl font-bold tabular-nums text-app-text">{{ number_format($analyzed) }}</p>
        </x-ui.card>
        <x-ui.card title="Average score">
            <p class="text-4xl font-bold tabular-nums text-app-text">{{ $avgScore }}</p>
        </x-ui.card>
        <x-ui.card title="Strong matches">
            <p class="text-4xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $distribution[0]['count'] + $distribution[1]['count'] }}</p>
            <p class="text-xs text-muted">Excellent + Good</p>
        </x-ui.card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Score distribution">
            @if ($analyzed === 0)
                <p class="text-sm text-muted">No scored candidates yet.</p>
            @else
                <div class="space-y-4">
                    @foreach ($distribution as $d)
                        @php $pct = $analyzed > 0 ? round($d['count'] / $analyzed * 100) : 0; @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="text-app-text">{{ $d['label'] }} <span class="text-muted">({{ $d['range'] }})</span></span>
                                <span class="tabular-nums font-medium text-app-text">{{ $d['count'] }} · {{ $pct }}%</span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-line/60">
                                <div class="h-full rounded-full {{ $barColors[$d['color']] }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Top missing skills">
            @if (empty($topMissing))
                <p class="text-sm text-muted">No skill gaps recorded yet.</p>
            @else
                <div class="space-y-3">
                    @foreach ($topMissing as $row)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="text-app-text">{{ $row['skill'] }}</span>
                                <span class="tabular-nums text-muted">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-line/60">
                                <div class="h-full rounded-full bg-amber-500" style="width: {{ round($row['count'] / $maxMissing * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="By job" :padding="false">
        @if ($perJob->isEmpty())
            <x-ui.empty title="No jobs yet" description="Create jobs and screen candidates to see per-job insights." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead><tr><x-ui.th>Job</x-ui.th><x-ui.th>Analyzed</x-ui.th><x-ui.th>Avg score</x-ui.th><x-ui.th>Top score</x-ui.th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($perJob as $row)
                        <tr class="hover:bg-primary-soft/30">
                            <x-ui.td><a href="{{ route('app.reports.show', $row['job']) }}" class="font-medium text-app-text hover:text-primary">{{ $row['job']->title }}</a></x-ui.td>
                            <x-ui.td class="tabular-nums text-muted">{{ $row['analyzed'] }}</x-ui.td>
                            <x-ui.td class="tabular-nums text-muted">{{ $row['avg'] ?: '—' }}</x-ui.td>
                            <x-ui.td>@if ($row['top'] !== null)<x-ui.score-pill :score="$row['top']" :show-label="false" />@else<span class="text-muted">—</span>@endif</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
