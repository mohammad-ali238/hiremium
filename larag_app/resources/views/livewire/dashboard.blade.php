<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-app-text">Welcome back, {{ auth()->user()->name ?? 'there' }}</h1>
        <p class="mt-1 text-sm text-muted">Here's what's happening in your workspace.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Total Candidates', number_format($stats['candidates']), 'users', route('app.candidates')],
            ['Jobs', number_format($stats['jobs']), 'briefcase', route('app.jobs')],
            ['Top Matches', number_format($stats['topMatches']), 'star', route('app.candidates')],
            ['AI Usage', number_format($stats['creditsTotal']), 'credit', route('app.usage')],
        ] as [$label, $value, $ic, $href])
            <a href="{{ $href }}" class="block rounded-xl border border-line bg-card p-5 transition hover:border-primary/40">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-muted">{{ $label }}</p>
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-primary-soft text-primary">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            @switch($ic)
                                @case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/> @break
                                @case('briefcase') <rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/> @break
                                @case('star') <path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 18l-5.8 3 1.1-6.5L2.6 9.8l6.5-.9z"/> @break
                                @default <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
                            @endswitch
                        </svg>
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-app-text">{{ $value }}</p>
                @if ($label === 'AI Usage' && $monthlyTotal > 0)
                    <div class="mt-3"><x-ui.progress :value="(int) round(($monthlyTotal - $creditsMonthly) / max(1, $monthlyTotal) * 100)" /></div>
                @endif
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Best candidates">
                <x-slot:actions><a href="{{ route('app.candidates') }}" class="text-sm font-medium text-primary hover:underline">View all</a></x-slot:actions>
                @if ($bestCandidates->isEmpty())
                    <p class="text-sm text-muted">No scored candidates yet. Upload resumes to a job to get started.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($bestCandidates as $m)
                            <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <x-ui.avatar :name="$m->candidate?->name ?? '?'" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('app.candidates.show', $m) }}" class="font-medium text-app-text hover:text-primary">{{ $m->candidate?->name ?? 'Unknown' }}</a>
                                    <p class="truncate text-xs text-muted">{{ $m->job?->title }} · {{ \Illuminate\Support\Str::limit($m->reason, 70) }}</p>
                                </div>
                                <x-ui.score-pill :score="$m->score" :show-label="false" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Recent jobs" :padding="false">
                @if ($recentJobs->isEmpty())
                    <x-ui.empty title="No jobs yet" description="Create a job to start screening candidates.">
                        <x-slot:action><x-ui.button size="sm" href="{{ route('app.jobs.create') }}">New job</x-ui.button></x-slot:action>
                    </x-ui.empty>
                @else
                    <x-ui.table class="rounded-none border-0">
                        <thead><tr><x-ui.th>Job</x-ui.th><x-ui.th>Candidates</x-ui.th><x-ui.th>Top score</x-ui.th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($recentJobs as $row)
                                <tr class="hover:bg-primary-soft/30">
                                    <x-ui.td><a href="{{ route('app.jobs.edit', $row['job']) }}" class="font-medium text-app-text hover:text-primary">{{ $row['job']->title }}</a></x-ui.td>
                                    <x-ui.td class="text-muted tabular-nums">{{ $row['candidates'] }}</x-ui.td>
                                    <x-ui.td>@if ($row['top'] !== null)<x-ui.score-pill :score="$row['top']" :show-label="false" />@else<span class="text-muted">—</span>@endif</x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <x-ui.card title="AI screening summary">
                <p class="text-sm text-muted"><span class="font-semibold text-app-text tabular-nums">{{ $screening['analyzed'] }}</span> candidates analyzed</p>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-sm"><span class="text-muted">Excellent matches</span><span class="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $screening['excellent'] }}</span></div>
                    <div class="flex items-center justify-between text-sm"><span class="text-muted">Good matches</span><span class="font-semibold tabular-nums text-indigo-600 dark:text-indigo-400">{{ $screening['good'] }}</span></div>
                    <div class="flex items-center justify-between text-sm"><span class="text-muted">Low-fit candidates</span><span class="font-semibold tabular-nums text-rose-600 dark:text-rose-400">{{ $screening['low'] }}</span></div>
                </div>
                @if (! empty($topMissing))
                    <div class="mt-5 border-t border-line pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">Top missing skills</p>
                        <div class="flex flex-wrap gap-1.5">@foreach ($topMissing as $skill)<x-ui.badge color="warning">{{ $skill }}</x-ui.badge>@endforeach</div>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Recent activity">
                <x-slot:actions><a href="{{ route('app.activity') }}" class="text-sm font-medium text-primary hover:underline">View all</a></x-slot:actions>
                @if ($recent->isEmpty())
                    <p class="text-sm text-muted">No activity yet.</p>
                @else
                    <ul class="space-y-4">@foreach ($recent as $activity)<li><x-activity-item :activity="$activity" /></li>@endforeach</ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
