<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-app-text">Jobs</h1>
            <p class="mt-1 text-sm text-muted">{{ $jobsUsed }} of {{ $maxJobs }} jobs used on your plan.</p>
        </div>
        @if ($canCreate)
            <x-ui.button href="{{ route('app.jobs.create') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New job
            </x-ui.button>
        @elseif ($canManage)
            <x-ui.tooltip text="You've reached your plan's job limit">
                <x-ui.button variant="secondary" disabled>New job</x-ui.button>
            </x-ui.tooltip>
        @endif
    </div>

    {{-- Filters --}}
    <x-ui.filter-bar>
        <x-slot:search>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Search jobs…" class="pl-9" />
            </div>
        </x-slot:search>
        <x-ui.select wire:model.live="status">
            <option value="">All statuses</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.filter-bar>

    <x-ui.card :padding="false">
        @if ($jobs->isEmpty())
            <x-ui.empty title="No jobs yet" description="Create a job with its required skills, then upload resumes to score candidates against it.">
                @if ($canCreate)
                    <x-slot:action><x-ui.button href="{{ route('app.jobs.create') }}">New job</x-ui.button></x-slot:action>
                @endif
            </x-ui.empty>
        @else
            <x-ui.table class="rounded-none border-0">
                <thead>
                    <tr>
                        <x-ui.th>Job</x-ui.th>
                        <x-ui.th>Skills</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th>Created</x-ui.th>
                        <x-ui.th class="text-right">Actions</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($jobs as $job)
                        <tr wire:key="job-{{ $job->id }}" class="hover:bg-primary-soft/30">
                            <x-ui.td>
                                <p class="font-medium text-app-text">
                                    @if ($canManage)
                                        <a href="{{ route('app.jobs.edit', $job) }}" wire:navigate class="hover:text-primary hover:underline">{{ $job->title }}</a>
                                    @else
                                        {{ $job->title }}
                                    @endif
                                </p>
                                {{-- Per-job stats: matches (links to the candidates page filtered to this job), batches, decisions --}}
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted">
                                    <a href="{{ route('app.candidates', ['jobFilter' => $job->id]) }}" wire:navigate
                                       class="inline-flex items-center gap-1 font-medium text-primary hover:underline" title="View matched candidates for this job">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        {{ $job->matches_count }} {{ \Illuminate\Support\Str::plural('match', $job->matches_count) }}
                                    </a>
                                    <span class="inline-flex items-center gap-1" title="Upload batches">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 9 5-5 5 5"/><path d="M12 4v12"/></svg>
                                        {{ $job->batches_count }} {{ \Illuminate\Support\Str::plural('batch', $job->batches_count) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 {{ $job->shortlisted_count ? 'text-success' : '' }}" title="Shortlisted">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                        Shortlisted: {{ $job->shortlisted_count }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 {{ $job->rejected_count ? 'text-danger' : '' }}" title="Rejected">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        Rejected: {{ $job->rejected_count }}
                                    </span>
                                </p>
                            </x-ui.td>
                            <x-ui.td class="text-muted">
                                {{ count($job->must_have_skills ?? []) }} must-have · {{ count($job->nice_to_have_skills ?? []) }} nice-to-have
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :color="$job->status === 'open' ? 'success' : ($job->status === 'closed' ? 'gray' : 'warning')">
                                    {{ $statuses[$job->status] ?? $job->status }}
                                </x-ui.badge>
                            </x-ui.td>
                            <x-ui.td class="text-muted">{{ $job->created_at?->diffForHumans() }}</x-ui.td>
                            <x-ui.td class="text-right">
                                @if ($canManage)
                                    <div class="inline-flex items-center justify-end gap-1">
                                        <x-ui.button variant="ghost" size="sm" href="{{ route('app.upload', ['job' => $job->id]) }}">Upload Resumes</x-ui.button>

                                        {{-- More actions: Edit / Duplicate / Delete --}}
                                        <x-ui.dropdown align="right" width="w-44">
                                            <x-slot:trigger>
                                                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-primary-soft/60 hover:text-app-text" aria-label="More actions" title="More actions">
                                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                                </button>
                                            </x-slot:trigger>

                                            <a href="{{ route('app.jobs.edit', $job) }}" wire:navigate
                                               class="flex items-center gap-2.5 px-3 py-2 text-sm text-app-text hover:bg-primary-soft/40">
                                                <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                                Edit
                                            </a>
                                            <button type="button" wire:click="duplicate({{ $job->id }})" wire:loading.attr="disabled" wire:target="duplicate({{ $job->id }})"
                                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-primary hover:bg-primary-soft/40 disabled:opacity-60">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                Duplicate
                                            </button>
                                            <div class="my-1 border-t border-line"></div>
                                            <button type="button"
                                                    x-on:click="$store.confirm.ask({ title: 'Delete job?', text: 'This removes the job and its candidate matches.', danger: true, confirmText: 'Delete' }).then(ok => ok && $wire.delete({{ $job->id }}))"
                                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-danger hover:bg-danger/10">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                                Delete
                                            </button>
                                        </x-ui.dropdown>
                                    </div>
                                @endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($jobs->hasPages())
        <div>{{ $jobs->links() }}</div>
    @endif
</div>
