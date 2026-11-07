@php
    $profile = (array) ($report['skill_profile'] ?? []);
    $candidate = (array) ($report['candidate'] ?? []);

    $industry = trim((string) ($profile['industry'] ?? ''));
    $subIndustry = trim((string) ($profile['sub_industry'] ?? ''));
    $seniority = trim((string) ($profile['seniority'] ?? ''));
    $about = trim((string) ($profile['about'] ?? ''));

    // The three badge lists, rendered by one loop so they stay consistent.
    $skillGroups = [
        [
            'key' => 'core_skills',
            'title' => 'Core skills',
            'blurb' => 'The hard skills the resume actually evidences.',
            'color' => 'primary',
            'icon' => 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437',
        ],
        [
            'key' => 'soft_skills',
            'title' => 'Soft skills',
            'blurb' => 'Only the ones the work history backs up.',
            'color' => 'success',
            'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
        ],
        [
            'key' => 'tools',
            'title' => 'Tools & platforms',
            'blurb' => 'Named software this candidate has used.',
            'color' => 'gray',
            'icon' => 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63',
        ],
    ];
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

{{-- Headline: who this is --}}
<div class="print-keep print-plain rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Skills profile</p>
    <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">
        {{ $candidate['fullName'] ?: 'This candidate' }}
    </h1>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @if ($candidate['jobTitle'] ?? false)
            <x-ui.badge color="gray">{{ $candidate['jobTitle'] }}</x-ui.badge>
        @endif
        @if ($seniority)
            <x-ui.badge color="primary">{{ $seniority }}</x-ui.badge>
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
    {{-- Core skills, soft skills, tools --}}
    @foreach ($skillGroups as $group)
        @php $items = array_values(array_filter((array) ($profile[$group['key']] ?? []))); @endphp
        @if ($items)
            <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-slate-50 text-slate-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $group['icon'] }}" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-900">{{ $group['title'] }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $group['blurb'] }}</p>
                    </div>
                    <span class="ml-auto shrink-0 text-xs font-medium text-slate-400">{{ count($items) }}</span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($items as $item)
                        <x-ui.badge :color="$group['color']" size="sm">{{ $item }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

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

    {{-- About this candidate — last, because it reads as the conclusion. --}}
    @if ($about)
        <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold text-slate-900">About this candidate</h2>
            <p class="mt-1 text-xs text-slate-500">Written from the whole resume, not from any summary the candidate wrote.</p>
            <p class="mt-4 max-w-3xl leading-relaxed text-slate-700">{{ $about }}</p>
        </div>
    @endif

    @if (! $about && ! $industry && empty($profile['core_skills']))
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

{{-- The resume exactly as it was read — everything the profile above came from. --}}
<x-ui.modal name="tool-resume" title="Resume (as we read it)" max-width="2xl">
    <x-ui.resume-snapshot :resume="$candidate"
                          note="This is everything we could read from the file. Anything missing here was not in the resume — the profile above is built only from what is shown." />
</x-ui.modal>
