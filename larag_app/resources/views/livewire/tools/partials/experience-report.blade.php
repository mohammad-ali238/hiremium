@php
    $profile = (array) ($report['experience_profile'] ?? []);
    $candidate = (array) ($report['candidate'] ?? []);
    $rows = (array) ($report['experience_rows'] ?? []);
    $total = (array) ($report['total_experience'] ?? []);

    $industry = trim((string) ($profile['industry'] ?? ''));
    $subIndustry = trim((string) ($profile['sub_industry'] ?? ''));
    $seniority = trim((string) ($profile['seniority'] ?? ''));
    $about = trim((string) ($profile['about'] ?? ''));
    $totalLabel = trim((string) ($total['label'] ?? ''));
@endphp

{{-- Toolbar --}}
<div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">
        Generated {{ optional($this->run->completed_at)->format('M j, Y') }} · this link is yours to keep and share
    </p>
    <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
        <button type="button" x-data @click="$dispatch('open-modal', 'tool-resume')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            View resume
        </button>

        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.4 42.4 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" /></svg>
            Print
        </button>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Download PDF
        </button>
        <button type="button"
                @click="navigator.clipboard.writeText(@js($this->shareUrl)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
            <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
        </button>
    </div>
</div>

{{-- Headline --}}
<div class="print-keep print-plain rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Experience profile</p>
    <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">
        {{ $candidate['fullName'] ?: 'This candidate' }}
    </h1>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        @if ($candidate['jobTitle'] ?? false)
            <x-ui.badge color="gray">{{ $candidate['jobTitle'] }}</x-ui.badge>
        @endif
        @if ($totalLabel)
            <x-ui.badge color="primary">{{ $totalLabel }}</x-ui.badge>
        @endif
        {{-- How certain we are that we read the resume correctly. --}}
        <x-ui.confidence-pill :confidence="$report['parse_confidence'] ?? ($candidate['confidence'] ?? null)" size="sm" />
        <button type="button" x-data @click="$dispatch('open-modal', 'tool-resume')"
                class="no-print text-xs font-medium text-indigo-600 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-500">
            See the full resume we read
        </button>
    </div>
</div>

<div class="mt-6 space-y-6">
    {{-- Experiences: one line each, nothing else --}}
    <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Experience</h2>
                <p class="mt-0.5 text-xs text-slate-500">Every role on the resume, newest first.</p>
            </div>
            <span class="shrink-0 text-xs font-medium text-slate-400">{{ count($rows) }}</span>
        </div>

        @if ($rows)
            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 py-2.5 text-sm">
                        <span class="font-medium text-slate-900">{{ $row['title'] }}</span>
                        @if (! empty($row['company']))
                            <span class="text-slate-500">· {{ $row['company'] }}</span>
                        @endif
                        @if (! empty($row['from']) || ! empty($row['to']))
                            <span class="text-slate-500">
                                · {{ trim(($row['from'] ?? '').' – '.($row['to'] ?? ''), ' –') }}
                            </span>
                        @endif
                        @if (! empty($row['duration']))
                            <span class="ml-auto shrink-0 text-xs font-medium text-indigo-600">{{ $row['duration'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-4 text-sm text-slate-500">No dated roles could be read from this resume.</p>
        @endif
    </div>

    {{-- Overall experience — how long, and at what level --}}
    @if ($totalLabel || $seniority)
        <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold text-slate-900">Overall experience</h2>

            <div class="mt-4 grid gap-6 sm:grid-cols-2">
                @if ($totalLabel)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total</p>
                        <p class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">{{ $totalLabel }}</p>
                        <p class="mt-1.5 text-xs text-slate-500">Counted across every role, with overlapping ones merged so concurrent jobs are not double counted.</p>
                    </div>
                @endif

                @if ($seniority)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Seniority level</p>
                        <p class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">{{ $seniority }}</p>
                        <p class="mt-1.5 text-xs text-slate-500">The level this candidate operates at, judged from scope and ownership rather than job title.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Industry / sector --}}
    @if ($industry || $subIndustry)
        <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold text-slate-900">Industry / sector</h2>
            <p class="mt-1 text-xs text-slate-500">The sector this person works in, not the job they do.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($industry)<x-ui.badge color="primary">{{ $industry }}</x-ui.badge>@endif
                @if ($subIndustry)<x-ui.badge color="gray">{{ $subIndustry }}</x-ui.badge>@endif
            </div>
        </div>
    @endif

    {{-- About, last --}}
    @if ($about)
        <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold text-slate-900">About this candidate</h2>
            <p class="mt-1 text-xs text-slate-500">Written from the whole resume, not from any summary the candidate wrote.</p>
            <p class="mt-4 max-w-3xl leading-relaxed text-slate-700">{{ $about }}</p>
        </div>
    @endif

    @if (! $rows && ! $about && ! $industry)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
            We could not read enough from this file to build a profile. A text-based PDF or DOCX
            usually works better than a scan or a screenshot.
        </div>
    @endif
</div>

{{-- CTA --}}
<div class="no-print mt-8 rounded-2xl bg-gradient-to-br from-indigo-600 to-sky-500 p-6 text-center text-white sm:p-8">
    <h2 class="text-xl font-bold">Doing this for a whole folder of CVs?</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-indigo-100">Hiremium extracts and scores every resume against your job automatically — bulk upload, ranked shortlists, per-candidate reports.</p>
    <a href="{{ $this->ctaUrl }}" class="mt-5 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50">Get started free</a>
</div>

{{-- The resume exactly as it was read. --}}
<x-ui.modal name="tool-resume" title="Resume (as we read it)" max-width="2xl">
    <x-ui.resume-snapshot :resume="$candidate"
                          note="This is everything we could read from the file. Anything missing here was not in the resume — the report above is built only from what is shown." />
</x-ui.modal>
