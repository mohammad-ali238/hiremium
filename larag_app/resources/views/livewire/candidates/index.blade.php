<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-app-text">{{ $shortlistView ? 'Shortlists' : 'Matched Candidates' }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $matches->total() }} scored candidate{{ $matches->total() === 1 ? '' : 's' }}.</p>
        </div>
        {{-- Only when there is actually something to export (respects active filters). --}}
        @if ($matches->total() > 0)
            <x-ui.button variant="secondary" size="sm" wire:click="export">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                Export CSV
            </x-ui.button>
        @endif
    </div>

    {{-- Filters --}}
    <x-ui.filter-bar>
        <x-slot:search>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Search candidates…" class="pl-9" />
            </div>
        </x-slot:search>
        @unless ($shortlistView)
            <x-ui.select wire:model.live="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </x-ui.select>
        @endunless
        <x-ui.select wire:model.live="jobFilter">
            <option value="">All jobs</option>
            @foreach ($jobs as $job)<option value="{{ $job->id }}">{{ $job->title }}</option>@endforeach
        </x-ui.select>
        <x-ui.select wire:model.live="band">
            <option value="">All scores</option>
            <option value="excellent">Excellent (90+)</option>
            <option value="good">Good (75–89)</option>
            <option value="fair">Fair (60–74)</option>
            <option value="low">Low fit (&lt;60)</option>
        </x-ui.select>
        <x-ui.select wire:model.live="sort">
            <option value="score">Sort: Score</option>
            <option value="name">Sort: Name</option>
            <option value="date">Sort: Newest</option>
        </x-ui.select>
    </x-ui.filter-bar>

    {{-- Bulk bar --}}
    @if ($canManage && ! empty($selected))
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-line bg-primary-soft/40 px-4 py-2.5">
            <span class="text-sm font-medium text-app-text">{{ count($selected) }} selected</span>
            <x-ui.button size="sm" variant="secondary" wire:click="compare">Compare</x-ui.button>
            <x-ui.button size="sm" variant="secondary" wire:click="bulkStatus('shortlisted')">Shortlist</x-ui.button>
            <x-ui.button size="sm" variant="secondary" wire:click="bulkStatus('reviewing')">Mark reviewing</x-ui.button>
            <x-ui.button size="sm" variant="secondary" wire:click="bulkStatus('rejected')">Reject</x-ui.button>
        </div>
    @endif

    <x-ui.card :padding="false">
        @if ($matches->isEmpty())
            <x-ui.empty title="No candidates yet" description="Upload resumes to a job — scored candidates appear here." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead>
                    <tr>
                        @if ($canManage)<x-ui.th class="w-10"></x-ui.th>@endif
                        <x-ui.th>Candidate</x-ui.th>
                        <x-ui.th>Match</x-ui.th>
                        <x-ui.th>Skills</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th>AI recommendation</x-ui.th>
                        <x-ui.th class="text-right">Actions</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($matches as $m)
                        <tr wire:key="m-{{ $m->id }}" class="hover:bg-primary-soft/30">
                            @if ($canManage)
                                <x-ui.td><input type="checkbox" wire:model.live="selected" value="{{ $m->id }}" class="h-4 w-4 rounded border-line text-primary focus:ring-brand"></x-ui.td>
                            @endif
                            <x-ui.td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$m->candidate?->name ?? '?'" size="sm" />
                                    <div class="min-w-0">
                                        <p class="font-medium text-app-text">{{ $m->candidate?->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-muted">{{ $m->candidate?->email ?? '—' }} · <button type="button" wire:click="showJob({{ $m->id }})" class="hover:text-primary hover:underline" title="Job details as analyzed">{{ $m->batch?->job_snapshot['title'] ?? $m->job?->title }}</button></p>
                                    </div>
                                </div>
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.score-pill :score="$m->score" />
                            </x-ui.td>
                            <x-ui.td class="text-muted">{{ count($m->matched_skills ?? []) }} matched · {{ count($m->missing_skills ?? []) }} missing</x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :color="match($m->status) { 'shortlisted' => 'success', 'rejected' => 'danger', 'hired' => 'primary', 'reviewing' => 'warning', default => 'gray' }">{{ $statuses[$m->status] ?? $m->status }}</x-ui.badge>
                            </x-ui.td>
                            <x-ui.td class="text-muted">{{ \App\Models\CandidateJobMatch::categoryLabels()[$m->category] ?? '—' }}</x-ui.td>
                            <x-ui.td class="text-right">
                                <x-ui.button variant="ghost" size="sm" href="{{ route('app.candidates.show', $m) }}">View</x-ui.button>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($matches->hasPages())<div>{{ $matches->links() }}</div>@endif

    <x-ui.modal name="job-snapshot" max-width="2xl" title="Job details (as analyzed)">
        <x-ui.job-snapshot :snapshot="$jobSnapshot" />
    </x-ui.modal>
</div>
