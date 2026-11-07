<div class="space-y-6" @if (in_array($batch->status, ['processing', 'extracting', 'extracting_claimed'])) wire:poll.3s @else wire:poll.visible.15s="checkAlive" @endif>
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Resume Upload', 'url' => route('app.upload')], ['label' => 'Batch #'.$batch->id]]" class="mb-2" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-app-text">{{ $batch->job?->title ?? 'Batch' }}</h1>
                <x-ui.batch-status :status="$batch->status" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" size="sm" x-data @click="$dispatch('open-modal', 'job-snapshot')" title="The job exactly as it was when this batch was analyzed">
                    <svg class="mr-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                    Job details
                </x-ui.button>
                @if ($creditFailed > 0)
                    {{-- Credit-failures are NOT auto-resumed after a top-up; the user resumes explicitly. --}}
                    <x-ui.button size="sm" wire:click="resumeCreditFailures" wire:loading.attr="disabled" wire:target="resumeCreditFailures"
                                 title="These CVs stopped because your AI usage ran out — they are fine and just need to be run again.">
                        <svg class="mr-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
                        Resume {{ $creditFailed }} waiting on AI usage
                    </x-ui.button>
                @endif
                @if ($errorFailed > 0)
                    <x-ui.button variant="secondary" size="sm" wire:click="retryFailed">Retry failed ({{ $errorFailed }})</x-ui.button>
                @endif
            </div>
        </div>
        <p class="mt-1 text-sm text-muted">
            {{ $batch->total_files }} file(s) · uploaded {{ $batch->created_at?->diffForHumans() }}
            @if ($creditsUsed > 0) · <span class="font-medium text-app-text">{{ $creditsUsed }} AI request(s) used</span> @endif
        </p>
    </div>

    @if (in_array($batch->status, ['extracting', 'extracting_claimed']))
        {{-- Queued intake in progress: the raw upload is being expanded/validated/split by a background job. --}}
        <div class="flex items-center gap-3 rounded-xl border border-primary/30 bg-primary-soft/30 px-5 py-4">
            <x-ui.loader size="sm" />
            <div>
                <p class="text-sm font-semibold text-app-text">Preparing your upload…</p>
                <p class="text-xs text-muted">{{ $intake['files'] ?? 0 }} file(s) received. ZIPs are being extracted and checked; CVs will appear below as they are stored. This page updates automatically.</p>
            </div>
        </div>
    @elseif (($intake['error'] ?? null))
        <div class="rounded-xl border border-danger/30 bg-danger/5 px-5 py-4">
            <p class="text-sm font-semibold text-danger">This upload could not be prepared</p>
            <p class="text-xs text-muted">{{ $intake['error'] }} — please try uploading again.</p>
        </div>
    @endif

    @if ($siblingBatches->isNotEmpty() || (($intake['batches'] ?? 1) > 1))
        {{-- This upload was split across several batches (plan per-batch cap / intake ceilings): show where everything is. --}}
        <x-ui.card title="This upload" :padding="false">
            <div class="px-5 py-3 text-xs text-muted">
                @php $allBatches = $siblingBatches->count() + 1; $totalCvs = (int) $batch->total_files + (int) $siblingBatches->sum('total_files'); @endphp
                {{ $totalCvs }} CV(s) across {{ $allBatches }} batches.
                <span class="font-medium text-app-text">{{ $batch->total_files }} processing here</span>,
                {{ $siblingBatches->sum('total_files') }} in the other {{ $siblingBatches->count() }} batch(es) listed below.
            </div>
            <ul class="divide-y divide-line border-t border-line {{ $siblingBatches->count() > 10 ? 'max-h-80 overflow-y-auto' : '' }}">
                @foreach ($siblingBatches as $sb)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5" wire:key="sib-{{ $sb->id }}">
                        <div class="min-w-0">
                            <a href="{{ route('app.batches.show', $sb) }}" class="text-sm font-medium text-app-text hover:text-primary">Batch #{{ $sb->id }}</a>
                            <p class="text-xs text-muted">
                                {{ $sb->total_files }} file(s) · {{ $sb->analyzed_count }} analyzed
                                @if ($sb->failed_count)
                                    · {{ $sb->failed_count }} failed
                                @endif
                            </p>
                        </div>
                        <x-ui.batch-status :status="$sb->status" size="sm" />
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if (! empty($rejected))
        {{-- Files the user selected that were NOT stored (too large, wrong type, duplicate, over cap…).
             Shows the first 3; "Show more" reveals the rest; the list scrolls once it grows past ~10 rows. --}}
        <div x-data="{ open: false }"
             class="rounded-xl border border-warning/40 bg-warning/5">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 shrink-0 text-warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.3 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.3a2 2 0 0 0-3.4 0z"/></svg>
                    <h2 class="text-sm font-semibold text-app-text">Skipped files</h2>
                    <x-ui.badge color="warning" size="sm">{{ count($rejected) }}</x-ui.badge>
                </div>
                <p class="text-xs text-muted">These were not uploaded or analyzed and did not use any AI usage.</p>
            </div>
            <ul class="divide-y divide-warning/20 border-t border-warning/20 {{ count($rejected) > 10 ? 'max-h-72 overflow-y-auto' : '' }}">
                @foreach ($rejected as $i => $item)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-2.5"
                        @if ($i >= 3) x-show="open" x-cloak @endif>
                        <span class="min-w-0 truncate text-sm font-medium text-app-text" title="{{ $item['name'] ?? '' }}">{{ $item['name'] ?? '—' }}</span>
                        <span class="text-xs text-muted">{{ $item['reason'] ?? '' }}</span>
                    </li>
                @endforeach
            </ul>
            @if (count($rejected) > 3)
                <div class="border-t border-warning/20 px-5 py-2 text-center">
                    <button type="button" class="text-xs font-medium text-primary hover:underline" x-on:click="open = ! open">
                        <span x-show="! open">Show {{ count($rejected) - 3 }} more</span>
                        <span x-show="open" x-cloak>Show less</span>
                    </button>
                </div>
            @endif
        </div>
    @endif

    <x-ui.card title="Progress">
        <x-ui.progress :value="$batch->progressPercent()" :label="($batch->analyzed_count + $batch->failed_count + $creditFailed).' of '.$batch->total_files.' processed'" :show-value="true" class="mb-5" />
        <x-ui.stepper :stages="[
            ['label' => 'Uploaded', 'count' => $batch->total_files, 'state' => 'done'],
            ['label' => 'Parsed', 'count' => $batch->parsed_count, 'state' => $batch->status === 'processing' ? 'active' : ($batch->parsed_count > 0 ? 'done' : 'pending')],
            ['label' => 'Analyzed', 'count' => $batch->analyzed_count, 'state' => in_array($batch->status, ['completed','partial']) ? 'done' : ($batch->analyzed_count > 0 ? 'active' : 'pending')],
            ['label' => 'Failed', 'count' => $batch->failed_count, 'state' => $batch->failed_count > 0 ? 'failed' : 'pending'],
            ...($creditFailed > 0 ? [['label' => 'Waiting on AI usage', 'count' => $creditFailed, 'state' => 'active']] : []),
        ]" />
    </x-ui.card>

    <x-ui.card title="Files" :padding="false">
        @if ($batch->status === 'processing')
            <p class="border-b border-line px-5 py-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1.5"><x-ui.loader size="sm" /> Ranking live — top scorers move to the top as each CV is analyzed.</span>
            </p>
        @elseif ($batch->analyzed_count > 0)
            <p class="border-b border-line px-5 py-2 text-xs text-muted">Ranked by match score, highest first.</p>
        @endif
        @if ($files->isEmpty())
            <x-ui.empty title="No files stored" description="Every file was a duplicate or invalid." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead>
                    <tr>
                        <x-ui.th class="w-10">#</x-ui.th>
                        <x-ui.th>File</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th>Match</x-ui.th>
                        <x-ui.th class="text-right">CV</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($files as $file)
                        @php
                            $scored  = $file->score !== null;
                            $cm      = $file->candidate_id ? ($matchesByCandidate[$file->candidate_id] ?? null) : null;
                            $isBest  = $cm && $cm->resume_file_id === $file->id;
                            $otherCount = $file->candidate_id ? max(0, (int) ($siblingCounts[$file->candidate_id] ?? 1) - 1) : 0;
                        @endphp
                        <tr wire:key="rf-{{ $file->id }}">
                            <x-ui.td class="tabular-nums text-muted">
                                @if ($scored){{ ($files->firstItem() ?? 1) + $loop->index }}@else—@endif
                            </x-ui.td>
                            <x-ui.td>
                                <span class="font-medium text-app-text">{{ $file->original_name }}</span>
                                @if ($file->source === 'csv')
                                    <span class="ml-1 inline-flex items-center rounded bg-primary-soft px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary" title="Generated from a CSV-import row">CSV</span>
                                @endif
                                @if ($file->status === 'failed' && $file->error)
                                    @if ($file->failure_kind === 'credits')
                                        <p class="text-xs text-warning">Waiting on AI usage — top up, then use “Resume waiting on AI usage”.</p>
                                    @else
                                        <p class="text-xs text-danger">{{ $file->error }}</p>
                                    @endif
                                @endif
                                @if ($scored && $otherCount > 0)
                                    {{-- Same person as other file(s) in this batch: say which result the candidate keeps. --}}
                                    <p class="mt-0.5 text-[11px] text-muted">
                                        Same candidate as {{ $otherCount }} other file{{ $otherCount === 1 ? '' : 's' }} in this batch —
                                        @if ($isBest)
                                            <span class="font-medium text-success">this is the candidate's best score</span>
                                        @elseif ($cm && $cm->score !== null)
                                            best score kept ({{ $cm->score }})
                                        @endif
                                    </p>
                                @endif
                            </x-ui.td>
                            <x-ui.td>
                                @if ($file->status === 'failed' && $file->failure_kind === 'credits')
                                    <x-ui.badge color="warning">Waiting on AI usage</x-ui.badge>
                                @else
                                    <x-ui.batch-status :status="$file->status" />
                                @endif
                            </x-ui.td>
                            <x-ui.td>
                                @if ($scored)
                                    <x-ui.score-pill :score="$file->score" />
                                @elseif ($file->status === 'uploaded' || $file->status === 'parsed' || $file->status === 'submitted')
                                    <span class="inline-flex items-center gap-1.5 text-xs text-muted"><x-ui.loader size="sm" /> analyzing…</span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </x-ui.td>
                            <x-ui.td class="text-right">
                                @if ($scored)
                                    <button type="button" wire:click="showCandidate({{ $file->id }})" class="text-sm font-medium text-primary hover:underline">View</button>
                                @else
                                    <a href="{{ route('app.cv', $file) }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Download CV</a>
                                @endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @if ($files->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $files->links() }}</div>
            @endif
        @endif
    </x-ui.card>
    {{-- Candidate detail popup: score + identity + AI results only (no parsed-résumé data). --}}
    <x-ui.modal name="candidate-detail" max-width="2xl" :title="$viewing?->candidate?->name ?: 'Candidate'">
        @if ($viewing)
            @php $c = $viewing->candidate; $isCurrentBest = $viewingMatch && $viewingMatch->resume_file_id === $viewing->id; @endphp
            <div class="space-y-5" wire:key="viewing-{{ $viewing->id }}">
                {{-- Header: score + identity --}}
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        {{-- Name is already the modal title; show the role here. --}}
                        @if ($c?->job_title)<p class="text-base font-medium text-app-text">{{ $c->job_title }}</p>@endif
                        <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2"><dt class="sr-only">Job</dt><dd class="text-muted">Analyzed for <button type="button" class="font-medium text-primary hover:underline" x-data @click="$dispatch('open-modal', 'job-snapshot')">{{ $batch->job_snapshot['title'] ?? $batch->job?->title ?? 'this job' }}</button></dd></div>
                            @if ($c?->email)<div><dt class="sr-only">Email</dt><dd><a href="mailto:{{ $c->email }}" class="text-primary hover:underline">{{ $c->email }}</a></dd></div>@endif
                            @if ($c?->phone)<div><dt class="sr-only">Phone</dt><dd class="text-app-text">{{ $c->phone }}</dd></div>@endif
                            @if ($c?->location)<div><dt class="sr-only">Location</dt><dd class="text-muted">{{ $c->location }}</dd></div>@endif
                        </dl>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <x-ui.score-pill :score="$viewing->score" />
                        <x-ui.badge :color="match($viewing->category) { 'recommended' => 'success', 'consider' => 'warning', default => 'danger' }" size="sm">{{ ucfirst(str_replace('_', ' ', (string) $viewing->category)) }}</x-ui.badge>
                        @if ($viewingMatch && ! $isCurrentBest && $viewingMatch->score !== null)
                            <span class="text-[11px] text-muted">Candidate's best: {{ $viewingMatch->score }}</span>
                        @endif
                    </div>
                </div>

                {{-- AI analysis --}}
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted">AI analysis</p>
                    <p class="text-sm text-app-text">{{ $viewing->reason ?: 'No reasoning recorded.' }}</p>
                </div>

                {{-- Reasons to hire / reject — same adaptive logic as the candidate page --}}
                <x-ui.hire-reasons :score="$viewing->score" :strengths="$viewing->strengths ?? []" :concerns="$viewing->concerns ?? []" :compact="true" />

                <x-ui.must-have-coverage :coverage="$viewing->must_have_coverage ?? []" :compact="true" />

                <x-ui.score-breakdown :sub-scores="$viewing->sub_scores ?? []" :breakdown="$viewing->score_breakdown ?? []" :compact="true" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted">Matched skills</p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($viewing->matched_skills ?? [] as $sk)<x-ui.badge color="success" size="sm">{{ $sk }}</x-ui.badge>@empty<span class="text-sm text-muted">—</span>@endforelse
                        </div>
                    </div>
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted">Missing skills</p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($viewing->missing_skills ?? [] as $sk)<x-ui.badge color="danger" size="sm">{{ $sk }}</x-ui.badge>@empty<span class="text-sm text-muted">—</span>@endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <x-slot:footer>
            <div class="flex flex-wrap items-center justify-between gap-3">
                {{-- Left: how certain the parser was about this CV, as plain text. --}}
                @php $viewingConfidence = \App\Services\ResumeParsing\ParseConfidence::percent($viewing?->parse_confidence ?? $viewing?->candidate?->parse_confidence); @endphp
                @if ($viewingConfidence !== null)
                    <p class="text-sm text-muted">Resume Parsing Confidence: <span class="font-semibold tabular-nums text-app-text">{{ $viewingConfidence }}%</span></p>
                @else
                    <span></span>
                @endif

                {{-- Right: actions only. --}}
                <div class="flex flex-wrap items-center gap-4">
                    @if ($viewing)
                        <a href="{{ route('app.cv', $viewing) }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Download CV</a>
                    @endif
                    @if ($viewingMatch)
                        <x-ui.button size="sm" href="{{ route('app.candidates.show', $viewingMatch) }}" wire:navigate>Open full profile</x-ui.button>
                    @endif
                </div>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- The job as it was when this batch was created — frozen with the batch. --}}
    <x-ui.modal name="job-snapshot" max-width="2xl" title="Job details (as analyzed)">
        <x-ui.job-snapshot :snapshot="$batch->job_snapshot ?: $batch->job?->toSnapshot()" :taken-at="$batch->job_snapshot['snapshot_taken_at'] ?? $batch->created_at" />
    </x-ui.modal>
</div>
