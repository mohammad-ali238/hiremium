<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-app-text">Screening Reports</h1>
        <p class="mt-1 text-sm text-muted">A summary of screened candidates per job — view, print or export.</p>
    </div>

    <x-ui.card :padding="false">
        @if ($jobs->isEmpty())
            <x-ui.empty title="No jobs yet" description="Create a job and screen candidates to generate reports." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead><tr><x-ui.th>Job</x-ui.th><x-ui.th>Candidates</x-ui.th><x-ui.th>Top score</x-ui.th><x-ui.th class="text-right">Report</x-ui.th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($jobs as $job)
                        <tr class="hover:bg-primary-soft/30">
                            <x-ui.td><span class="font-medium text-app-text">{{ $job->title }}</span></x-ui.td>
                            <x-ui.td class="tabular-nums text-muted">{{ $job->analyzed_count }}</x-ui.td>
                            <x-ui.td>@if ($job->top_score !== null)<x-ui.score-pill :score="$job->top_score" :show-label="false" />@else<span class="text-muted">—</span>@endif</x-ui.td>
                            <x-ui.td class="text-right"><x-ui.button variant="ghost" size="sm" href="{{ route('app.reports.show', $job) }}">View report</x-ui.button></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($jobs->hasPages())<div>{{ $jobs->links() }}</div>@endif
</div>
