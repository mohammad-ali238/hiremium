<div class="max-w-3xl space-y-6">
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Jobs', 'url' => route('app.jobs')], ['label' => $isEdit ? 'Edit job' : 'New job']]" class="mb-2" />
        <h1 class="text-2xl font-bold text-app-text">{{ $isEdit ? 'Edit job' : 'New job' }}</h1>
        <p class="mt-1 text-sm text-muted">Structured requirements help the AI score candidates accurately.</p>
    </div>

    @if ($isEdit && $stats)
        {{-- Same stats as the jobs list: matches (→ candidates filtered to this job), batches, decisions. --}}
        <x-ui.card title="Overview">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <a href="{{ route('app.candidates', ['jobFilter' => $job->id]) }}" wire:navigate class="rounded-lg border border-line p-3 transition hover:border-primary hover:bg-primary-soft/30" title="View matched candidates for this job">
                    <p class="text-xs text-muted">Matches</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-primary">{{ $stats['matches'] }}</p>
                    <p class="text-[11px] text-primary">View candidates →</p>
                </a>
                <div class="rounded-lg border border-line p-3">
                    <p class="text-xs text-muted">Upload batches</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-app-text">{{ $stats['batches'] }}</p>
                </div>
                <div class="rounded-lg border border-line p-3">
                    <p class="text-xs text-muted">Shortlisted</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums {{ $stats['shortlisted'] ? 'text-success' : 'text-app-text' }}">{{ $stats['shortlisted'] }}</p>
                </div>
                <div class="rounded-lg border border-line p-3">
                    <p class="text-xs text-muted">Rejected</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums {{ $stats['rejected'] ? 'text-danger' : 'text-app-text' }}">{{ $stats['rejected'] }}</p>
                </div>
            </div>

            @if ($batchesTotal > 0)
                {{-- Collapsible uploads list: same look as the Upload page's "Recent uploads"; 10 at a time. --}}
                <div x-data="{ open: false }" class="mt-4">
                    <x-ui.button type="button" variant="secondary" size="sm" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span x-show="! open">Show uploads ({{ $batchesTotal }})</span>
                        <span x-show="open" x-cloak>Hide uploads</span>
                        <svg class="ml-1 h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </x-ui.button>

                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                         class="mt-3 overflow-hidden rounded-lg border border-line">
                        <ul class="divide-y divide-line">
                            @foreach ($batches as $b)
                                <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="job-batch-{{ $b->id }}">
                                    <div class="min-w-0">
                                        <a href="{{ route('app.batches.show', $b) }}" class="text-sm font-medium text-app-text hover:text-primary">Batch #{{ $b->id }}</a>
                                        <p class="text-xs text-muted">
                                            {{ $b->total_files }} file(s) · {{ $b->analyzed_count }} analyzed
                                            @if ($b->failed_count)
                                                · <span class="text-danger">{{ $b->failed_count }} failed</span>
                                            @endif
                                            · {{ $b->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                    <x-ui.batch-status :status="$b->status" />
                                </li>
                            @endforeach
                        </ul>
                        @if ($batchesHasMore)
                            <div class="border-t border-line px-4 py-2 text-center">
                                <button type="button" wire:click="loadMoreBatches" wire:loading.attr="disabled" wire:target="loadMoreBatches"
                                        class="text-xs font-medium text-primary hover:underline disabled:opacity-60">
                                    <span wire:loading.remove wire:target="loadMoreBatches">Load more ({{ $batchesTotal - $batches->count() }} older)</span>
                                    <span wire:loading wire:target="loadMoreBatches">Loading…</span>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </x-ui.card>
    @endif

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Details">
            <div class="space-y-5">
                <x-ui.field label="Job title" for="title" required :error="$errors->first('title')">
                    <x-ui.input id="title" wire:model="title" placeholder="Senior Laravel Developer" :error="$errors->has('title')" />
                </x-ui.field>

                <x-ui.field label="Description" for="description" hint="Responsibilities, context, anything the AI should weigh. Optional — but if filled, 50–8,000 characters." :error="$errors->first('description')">
                    <x-ui.rich-editor id="description" wire:model="description" :min="\App\Support\RichText::MIN_CHARS" :max="\App\Support\RichText::MAX_CHARS" placeholder="What the role involves…" />
                </x-ui.field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Employment type" for="employment_type">
                        <x-ui.select id="employment_type" wire:model="employment_type">
                            <option value="">—</option>
                            @foreach ($employmentTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Work mode" for="location_type" required>
                        <x-ui.select id="location_type" wire:model="location_type">
                            @foreach ($locationTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Location" for="location">
                        <x-ui.input id="location" wire:model="location" placeholder="Remote · London · …" />
                    </x-ui.field>
                    <x-ui.field label="Status" for="status" required>
                        <x-ui.select id="status" wire:model="status">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Requirements">
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Minimum experience (years)" for="min_experience_years" :error="$errors->first('min_experience_years')">
                        <x-ui.input id="min_experience_years" type="number" min="0" max="60" wire:model="min_experience_years" placeholder="3" />
                    </x-ui.field>
                    <x-ui.field label="Education" for="education">
                        <x-ui.input id="education" wire:model="education" placeholder="BSc Computer Science or equivalent" />
                    </x-ui.field>
                </div>

                <x-ui.field label="Must-have skills" hint="The AI penalises candidates missing these.">
                    <x-ui.tags model="mustHave" placeholder="e.g. Laravel, MySQL, AWS" />
                </x-ui.field>

                <x-ui.field label="Nice-to-have skills" hint="Bonus signals, not dealbreakers.">
                    <x-ui.tags model="niceToHave" placeholder="e.g. Docker, Vue" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button variant="secondary" href="{{ route('app.jobs') }}">Cancel</x-ui.button>
            <x-ui.button variant="secondary" type="button" wire:click="saveAndUpload" wire:loading.attr="disabled" wire:target="save,saveAndUpload">
                {{ $isEdit ? 'Save & Upload Resumes' : 'Create job & Upload Resumes' }}
            </x-ui.button>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save,saveAndUpload">{{ $isEdit ? 'Save changes' : 'Create job' }}</x-ui.button>
        </div>
    </form>
</div>
