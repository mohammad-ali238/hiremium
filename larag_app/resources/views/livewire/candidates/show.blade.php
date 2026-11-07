@php
    $c = $match->candidate;
    $d = $c?->parsed_data ?? [];
    // Parsing confidence of the CV this match was scored from, falling back to
    // the profile's own parse.
    $confidence = $match->resumeFile?->parse_confidence ?? $c?->parse_confidence;
@endphp
<div class="space-y-6">
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Matched Candidates', 'url' => route('app.candidates')], ['label' => $c?->name ?? 'Candidate']]" class="mb-2" />
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.avatar :name="$c?->name ?? '?'" size="lg" />
            <div>
                <h1 class="text-2xl font-bold text-app-text">{{ $c?->name ?? 'Unknown candidate' }}</h1>
                <p class="text-sm text-muted">
                    @if (! empty($d['jobTitle'])){{ $d['jobTitle'] }} · @endif
                    Viewing match for <button type="button" class="font-semibold text-app-text hover:text-primary hover:underline" x-data @click="$dispatch('open-modal', 'job-snapshot')" title="Job details as analyzed">{{ $match->batch?->job_snapshot['title'] ?? $match->job?->title ?? 'this job' }}</button>
                </p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <x-ui.score-pill :score="$match->score ?? 0" />
                <x-ui.badge :color="match($match->status) { 'shortlisted' => 'success', 'rejected' => 'danger', 'hired' => 'primary', 'reviewing' => 'warning', default => 'gray' }">{{ $statuses[$match->status] ?? $match->status }}</x-ui.badge>
            </div>
        </div>

        {{-- One person, several jobs: this page is ONE job's match. Show the others so it's
             obvious which analysis you're reading, and let the recruiter jump between them. --}}
        @if ($otherMatches->isNotEmpty())
            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-xs">
                <span class="font-medium text-muted">Also matched to:</span>
                @foreach ($otherMatches as $om)
                    <a href="{{ route('app.candidates.show', $om) }}" wire:navigate
                       class="inline-flex items-center gap-1.5 rounded-full border border-line bg-card px-2.5 py-1 text-app-text transition hover:border-primary hover:text-primary"
                       title="Open {{ $c?->name }}'s match for {{ $om->job?->title }}">
                        <span class="font-medium">{{ $om->job?->title ?? 'Job' }}</span>
                        @if ($om->score !== null)
                            <span class="tabular-nums text-muted">{{ $om->score }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </a>
                @endforeach
                <span class="ml-auto text-muted">{{ $otherMatches->count() + 1 }} jobs total · each has its own score &amp; reasons</span>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="AI analysis">
                <p class="text-sm text-app-text">{{ $match->reason ?: 'No reasoning recorded.' }}</p>

                {{-- Reasons to hire / reject — only rendered when the AI returned them; emphasis follows the score. --}}
                <x-ui.hire-reasons class="mt-5" :score="$match->score" :strengths="$match->strengths ?? []" :concerns="$match->concerns ?? []" />

                <x-ui.must-have-coverage class="mt-5" :coverage="$match->must_have_coverage ?? []" />

                <x-ui.score-breakdown class="mt-5" :sub-scores="$match->sub_scores ?? []" :breakdown="$match->score_breakdown ?? []" />

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">Matched skills</p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($match->matched_skills ?? [] as $s)<x-ui.badge color="success">{{ $s }}</x-ui.badge>@empty<span class="text-sm text-muted">—</span>@endforelse
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">Missing skills</p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($match->missing_skills ?? [] as $s)<x-ui.badge color="danger">{{ $s }}</x-ui.badge>@empty<span class="text-sm text-muted">—</span>@endforelse
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Resume">
                <div class="space-y-5 text-sm">
                    @if (\App\Services\ResumeParsing\ParseConfidence::percent($confidence) !== null)
                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                        <span>Read from the CV with</span>
                        <x-ui.confidence-pill :confidence="$confidence" size="sm" />
                        <span>certainty</span>
                    </div>
                    @endif
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-muted">
                        @if (!empty($d['email']))<span>✉ {{ $d['email'] }}</span>@endif
                        @if (!empty($d['phone']))<span>☎ {{ $d['phone'] }}</span>@endif
                    </div>
                    @if (!empty($d['summary']))
                        <p class="text-app-text">{{ $d['summary'] }}</p>
                    @endif
                    @if (!empty($d['skills']))
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">Skills</p>
                            <div class="flex flex-wrap gap-1.5">@foreach ($d['skills'] as $s)<x-ui.badge>{{ $s }}</x-ui.badge>@endforeach</div>
                        </div>
                    @endif
                    @if (!empty($d['experience']))
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">Experience</p>
                            <ul class="space-y-3">
                                @foreach ($d['experience'] as $e)
                                    <li>
                                        <p class="font-medium text-app-text">{{ $e['title'] ?? '' }} @if(!empty($e['company']))· {{ $e['company'] }}@endif</p>
                                        <p class="text-xs text-muted">{{ $e['startDate'] ?? '' }} – {{ $e['endDate'] ?? '' }}</p>
                                        @if (!empty($e['description']))<p class="mt-1 text-app-text">{{ $e['description'] }}</p>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (!empty($d['education']))
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">Education</p>
                            <ul class="space-y-2">
                                @foreach ($d['education'] as $ed)
                                    <li class="text-app-text">{{ trim(($ed['degree'] ?? '').' '.($ed['field'] ?? '')) }} @if(!empty($ed['institution']))· {{ $ed['institution'] }}@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </x-ui.card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            @if ($canManage)
                <x-ui.card title="Status">
                    <div class="flex flex-wrap gap-2">
                        @foreach (['new' => 'New', 'reviewing' => 'Reviewing', 'shortlisted' => 'Shortlist', 'hired' => 'Hire'] as $key => $label)
                            <x-ui.button size="sm" :variant="$match->status === $key ? 'primary' : 'secondary'" wire:click="changeStatus('{{ $key }}')">{{ $label }}</x-ui.button>
                        @endforeach
                    </div>
                    <div class="mt-4 border-t border-line pt-4">
                        <x-ui.field label="Rejection reason" for="rej">
                            <x-ui.textarea id="rej" wire:model="rejectionReason" rows="2" placeholder="Optional reason…" />
                        </x-ui.field>
                        <x-ui.button variant="danger" size="sm" class="mt-2 w-full" wire:click="changeStatus('rejected')">Reject candidate</x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            @if ($match->resumeFile)
                <x-ui.card title="Original CV">
                    <x-ui.button variant="secondary" size="sm" class="w-full" href="{{ route('app.cv', $match->resumeFile) }}" target="_blank">Download CV</x-ui.button>
                </x-ui.card>
            @endif

            {{-- All CVs analyzed for this candidate on this job. The analysis above
                 comes from the "current" (best-scoring) one; the rest are history. --}}
            @if ($cvHistory->count() > 1)
                <x-ui.card title="CV history" :subtitle="$cvHistory->count().' CVs analyzed for this job — the best score is shown above'" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($cvHistory as $cv)
                            @php $isCurrent = $cv->id === $match->resume_file_id; @endphp
                            <li class="flex items-center justify-between gap-3 px-5 py-3 {{ $isCurrent ? 'bg-primary-soft/25' : '' }}">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-app-text" title="{{ $cv->original_name }}">
                                        {{ $cv->original_name }}
                                        @if ($isCurrent)<x-ui.badge color="primary" size="sm">current</x-ui.badge>@endif
                                    </p>
                                    <p class="text-xs text-muted">
                                        Batch #{{ $cv->batch_id }} · {{ $cv->analyzed_at?->diffForHumans() ?? $cv->created_at?->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-ui.score-pill :score="$cv->score" />
                                    <a href="{{ route('app.cv', $cv) }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary hover:underline">Download</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card title="Notes">
                @if ($canManage)
                    <form wire:submit="addNote" class="space-y-2">
                        <x-ui.textarea wire:model="newNote" rows="2" placeholder="Add a note…" />
                        <x-ui.button type="submit" size="sm" class="w-full">Add note</x-ui.button>
                    </form>
                @endif
                <ul class="mt-4 space-y-3">
                    @forelse ($notes as $note)
                        <li class="text-sm">
                            <p class="text-app-text">{{ $note->body }}</p>
                            <p class="text-xs text-muted">{{ $note->user?->name ?? 'Someone' }} · {{ $note->created_at?->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-muted">No notes yet.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="job-snapshot" max-width="2xl" title="Job details (as analyzed)">
        <x-ui.job-snapshot :snapshot="$match->batch?->job_snapshot ?: $match->job?->toSnapshot()" :taken-at="$match->batch?->job_snapshot['snapshot_taken_at'] ?? $match->batch?->created_at" />
    </x-ui.modal>
</div>
