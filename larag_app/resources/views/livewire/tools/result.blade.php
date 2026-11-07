<div class="print-tight mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">

    @if ($expired)

        {{-- ------------------------------------------------------------------
             Never opened in time. Nothing ran, so there is nothing to show —
             but starting again is one click away.
        ------------------------------------------------------------------- --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-slate-100">
                <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <h1 class="mt-6 text-2xl font-bold text-slate-900">This link has expired</h1>
            <p class="mx-auto mt-3 max-w-md text-slate-600">
                Result links stay open for {{ \App\Support\ToolSettings::expiryHours() }} hours. This one was never opened, so nothing was analysed.
            </p>
            @if ($this->tool)
                <a href="{{ route('tools.show', $this->tool->slug()) }}"
                   class="mt-8 inline-flex items-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    Start a new check
                </a>
            @endif
        </div>

    @elseif ($this->run->status === \App\Models\ToolRun::STATUS_FAILED)

        {{-- ------------------------------------------------------------------
             Failed. Say what went wrong in plain words, and offer the action
             that can actually help: retry the failed stage, or start over when
             retrying cannot possibly work (an unreadable file stays unreadable).
        ------------------------------------------------------------------- --}}
        @php
            $code = (string) $this->run->failure_code;
            $reasons = [
                'unreadable_file' => "We couldn't read that file. It may be a scan we can't process, password-protected, or empty.",
                'low_confidence' => "We couldn't read this resume clearly enough to score it fairly — it may be scanned, low quality or incomplete. A clearer, text-based copy works best.",
                'missing_file' => 'The uploaded file is no longer available, so this report cannot be built.',
                'ai_error' => "The AI service didn't answer in time.",
                'timed_out' => 'This run stopped responding partway through.',
                'attempts_exhausted' => 'We tried this a few times and it kept failing.',
            ];
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-50">
                <svg class="h-8 w-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>
            <h1 class="mt-6 text-2xl font-bold text-slate-900">We couldn't finish this report</h1>
            <p class="mx-auto mt-3 max-w-md text-slate-600">
                {{ $reasons[$code] ?? 'Something went wrong while building your report.' }}
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @if ($this->canRetry)
                    <button type="button" wire:click="retry" wire:loading.attr="disabled" wire:target="retry"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-60">
                        <svg wire:loading wire:target="retry" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        Try again
                    </button>
                    <p class="w-full text-xs text-slate-500">We pick up where it stopped — the finished steps are not repeated.</p>
                @elseif ($this->tool)
                    <a href="{{ route('tools.show', $this->tool->slug()) }}"
                       class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                        Upload a different file
                    </a>
                @endif
            </div>
        </div>

    @elseif ($this->run->status === \App\Models\ToolRun::STATUS_COMPLETED)

        {{-- ------------------------------------------------------------------
             The report. Everything below prints: the toolbar and the call to
             action are marked no-print, so "Download PDF" produces the document
             and nothing around it.
        ------------------------------------------------------------------- --}}
        @php $report = $this->report; @endphp

        @if ($this->run->tool === 'ats-score-checker')
            @include('livewire.tools.partials.ats-report', ['report' => $report])
        @elseif ($this->run->tool === 'job-description-analyzer')
            @include('livewire.tools.partials.jd-report', ['report' => $report])
        @elseif ($this->run->tool === 'resume-skills-extractor')
            @include('livewire.tools.partials.skills-report', ['report' => $report])
        @elseif ($this->run->tool === 'resume-experience-extractor')
            @include('livewire.tools.partials.experience-report', ['report' => $report])
        @else
        @php
            $candidate = (array) ($report['candidate'] ?? []);
            $job = (array) ($report['job'] ?? []);
            $years = $report['experience_years'] ?? null;
            $subScores = (array) ($report['sub_scores'] ?? []);
        @endphp

        {{-- Toolbar --}}
        <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                Generated {{ optional($this->run->completed_at)->format('M j, Y') }} · this link is yours to keep and share
            </p>

            <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                <button type="button" x-data @click="$dispatch('open-modal', 'tool-job')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.1a2.25 2.25 0 01-2.25 2.25h-12A2.25 2.25 0 013.75 18.25v-12A2.25 2.25 0 016 4h4.1M15 3.75h5.25V9m0-5.25L9.75 14.25" />
                    </svg>
                    Job details
                </button>

                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.4 42.4 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" />
                    </svg>
                    Print
                </button>

                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download PDF
                </button>

                <button type="button"
                        @click="navigator.clipboard.writeText(@js($this->shareUrl)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                    </svg>
                    <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
                </button>
            </div>
        </div>

        {{-- Headline: the number, the job, the person --}}
        <div class="print-keep print-plain rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Match report</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">{{ $job['title'] ?? 'Your job' }}</h1>

                    @if (! empty($candidate['fullName']))
                        <p class="mt-2 text-slate-700">
                            <span class="font-semibold">{{ $candidate['fullName'] }}</span>
                            @if (! empty($candidate['jobTitle'])) · {{ $candidate['jobTitle'] }} @endif
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($years !== null)
                            <x-ui.badge color="gray">{{ (int) floor((float) $years) }} years of experience</x-ui.badge>
                        @endif
                        @if (! empty($job['min_experience_years']))
                            <x-ui.badge color="gray">{{ $job['min_experience_years'] }}+ required</x-ui.badge>
                        @endif
                        <button type="button" x-data @click="$dispatch('open-modal', 'tool-job')"
                                class="no-print text-xs font-medium text-indigo-600 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-500">
                            See the job you submitted
                        </button>
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-6xl font-extrabold leading-none tracking-tight text-slate-900">
                        {{ $report['score'] ?? '—' }}<span class="text-2xl text-slate-400">/100</span>
                    </div>
                    <x-ui.score-pill :score="$report['score'] ?? 0" class="mt-3" />
                    {{-- How certain we are that we read the resume correctly. --}}
                    @if (\App\Services\ResumeParsing\ParseConfidence::percent($report['parse_confidence'] ?? ($candidate['confidence'] ?? null)) !== null)
                    <div class="mt-2 flex justify-end">
                        <x-ui.confidence-pill :confidence="$report['parse_confidence'] ?? ($candidate['confidence'] ?? null)" />
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">resume read confidence</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- The analysis --}}
        <div class="print-plain mt-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">AI analysis</h2>

            <p class="mt-3 text-sm leading-relaxed text-slate-700">
                {{ $report['reason'] ?: 'No reasoning was recorded for this match.' }}
            </p>

            <x-ui.hire-reasons class="print-keep mt-6"
                               :score="$report['score'] ?? null"
                               :strengths="$report['strengths'] ?? []"
                               :concerns="$report['concerns'] ?? []" />

            <x-ui.must-have-coverage class="print-keep mt-6" :coverage="$report['must_have_coverage'] ?? []" />

            <x-ui.score-breakdown class="mt-6" :sub-scores="$subScores" :breakdown="(array) ($report['score_breakdown'] ?? [])" />

            <div class="print-keep mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Matched skills</p>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse ($report['matched_skills'] ?? [] as $skill)
                            <x-ui.badge color="success">{{ $skill }}</x-ui.badge>
                        @empty
                            <span class="text-sm text-slate-500">None found.</span>
                        @endforelse
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Missing skills</p>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse ($report['missing_skills'] ?? [] as $skill)
                            <x-ui.badge color="danger">{{ $skill }}</x-ui.badge>
                        @empty
                            <span class="text-sm text-slate-500">Nothing missing.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- The resume as the AI read it — the answer to "why did it say that?" --}}
        @if ($candidate)
            <div class="print-plain mt-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">The resume, as we read it</h2>
                <p class="mt-1 text-sm text-slate-500">This is what the scoring was based on. Anything missing here was not in the file.</p>

                <div class="mt-5 space-y-6 text-sm">
                    @if (! empty($candidate['email']) || ! empty($candidate['phone']))
                        <div class="flex flex-wrap gap-x-6 gap-y-1 text-slate-500">
                            @if (! empty($candidate['email']))<span>✉ {{ $candidate['email'] }}</span>@endif
                            @if (! empty($candidate['phone']))<span>☎ {{ $candidate['phone'] }}</span>@endif
                        </div>
                    @endif

                    @if (! empty($candidate['summary']))
                        <p class="text-slate-700">{{ $candidate['summary'] }}</p>
                    @endif

                    @if (! empty($candidate['skills']))
                        <div class="print-keep">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Skills</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($candidate['skills'] as $skill)<x-ui.badge>{{ $skill }}</x-ui.badge>@endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($candidate['experience']))
                        <div class="print-keep">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Experience</p>
                            <ul class="space-y-3">
                                @foreach ($candidate['experience'] as $role)
                                    <li>
                                        <p class="font-medium text-slate-900">
                                            {{ $role['title'] ?? '' }}@if (! empty($role['company'])) · {{ $role['company'] }}@endif
                                        </p>
                                        <p class="text-xs text-slate-500">{{ $role['startDate'] ?? '' }} – {{ $role['endDate'] ?? '' }}</p>
                                        @if (! empty($role['description']))
                                            <p class="mt-1 text-slate-700">{{ $role['description'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($candidate['education']))
                        <div class="print-keep">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Education</p>
                            <ul class="space-y-2">
                                @foreach ($candidate['education'] as $school)
                                    <li class="text-slate-700">
                                        {{ trim(($school['degree'] ?? '').' '.($school['field'] ?? '')) }}@if (! empty($school['institution'])) · {{ $school['institution'] }}@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Conversion --}}
        <div class="no-print mt-8 rounded-2xl bg-gradient-to-r from-indigo-600 to-sky-500 p-8 text-center">
            <h2 class="text-xl font-bold text-white">Screening more than one resume?</h2>
            <p class="mx-auto mt-2 max-w-lg text-indigo-50">
                Hiremium does this for a whole folder of CVs at once and ranks them best-first.
            </p>
            <a href="{{ $this->ctaUrl }}"
               class="mt-6 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50">
                Create a free account
            </a>
        </div>

        {{-- The job exactly as it was submitted — the other half of every score. --}}
        <x-ui.modal name="tool-job" title="Job details (as submitted)" max-width="2xl">
            <x-ui.job-snapshot :snapshot="$job"
                               note="This is the job exactly as it was submitted for this check. It is stored with the report, so the scoring above can always be read against it." />
        </x-ui.modal>
        @endif

    @elseif ($this->run->status === \App\Models\ToolRun::STATUS_AWAITING_OPEN)

        {{-- ------------------------------------------------------------------
             Not started yet, on purpose. Mail scanners and link previewers
             fetch this page before the visitor ever does; a click is the one
             thing they cannot fake, so nothing is analysed until it happens.
        ------------------------------------------------------------------- --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-indigo-50">
                <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>
            <h1 class="mt-6 text-2xl font-bold text-slate-900">Your check is ready to run</h1>
            <p class="mx-auto mt-3 max-w-md text-slate-600">
                We held off until you got here. Press the button and your resume is scored against the job — it usually takes under a minute.
            </p>

            @if ($busy)
                {{-- The day's ceiling is full. Nothing is lost: the link keeps
                     its place and the count resets at midnight. --}}
                <div class="mx-auto mt-6 max-w-md rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Our free tools have hit today's limit. Your link is still good — please come back and press the button again later.
                </div>
            @endif

            <button type="button" wire:click="start"
                    wire:loading.attr="disabled" wire:target="start"
                    class="mt-8 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="start">{{ $busy ? 'Try again' : 'See my result' }}</span>
                <span wire:loading wire:target="start">Starting…</span>
            </button>

            @if ($this->run->hoursUntilExpiry() !== null)
                <p class="mt-4 text-xs text-slate-500">
                    This link stays open for another {{ $this->run->hoursUntilExpiry() }} hours.
                </p>
            @endif
        </div>

    @else

        {{-- ------------------------------------------------------------------
             Processing. wire:init starts the first stage; each stage asks for
             the next one when it lands, so the bar moves as work completes
             rather than on a timer.
        ------------------------------------------------------------------- --}}
        <div wire:init="advance" class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-10">
            <div class="text-center">
                <h1 class="text-2xl font-bold text-slate-900">Building your report</h1>
                <p class="mt-2 text-slate-600">This usually takes under a minute. You can keep this tab open — or close it and open your link again later.</p>
            </div>

            <div class="mt-8">
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                         style="width: {{ max(4, (int) $this->run->progress) }}%"></div>
                </div>
                <p class="mt-2 text-right text-xs font-medium text-slate-500">{{ (int) $this->run->progress }}%</p>
            </div>

            <ol class="mt-8 space-y-4">
                @foreach ($this->steps as $step)
                    <li class="flex items-center gap-4">
                        <span @class([
                            'grid h-10 w-10 flex-none place-items-center rounded-xl',
                            'bg-emerald-50 text-emerald-600' => $step['state'] === 'done',
                            'bg-indigo-50 text-indigo-600' => $step['state'] === 'running',
                            'bg-amber-50 text-amber-600' => $step['state'] === 'failed',
                            'bg-slate-100 text-slate-400' => $step['state'] === 'waiting',
                        ])>
                            @if ($step['state'] === 'done')
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            @elseif ($step['state'] === 'running')
                                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                            @else
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}" />
                                </svg>
                            @endif
                        </span>
                        <span @class([
                            'text-sm',
                            'font-semibold text-slate-900' => $step['state'] === 'running',
                            'text-slate-600' => $step['state'] === 'done',
                            'text-slate-400' => $step['state'] === 'waiting',
                            'font-semibold text-amber-700' => $step['state'] === 'failed',
                        ])>{{ $step['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

    @endif
</div>
