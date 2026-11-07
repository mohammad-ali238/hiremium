@php
    $jd = (array) ($report['jd_result'] ?? []);
    $input = (array) $this->run->input_data;
    $score = (int) ($jd['score'] ?? 0);
    $level = \App\Services\Jd\SeniorityLevels::label((string) ($input['seniority'] ?? ''));
    $quality = $score >= 90 ? 'Excellent' : ($score >= 75 ? 'Strong' : ($score >= 60 ? 'Fair' : 'Needs work'));
    $qualityColor = $score >= 90 ? 'emerald' : ($score >= 75 ? 'indigo' : ($score >= 60 ? 'amber' : 'rose'));
    $pill = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-100',
    ][$qualityColor];

    // The submitted posting, shaped for <x-ui.job-snapshot> so this popup is
    // identical to the one on the Resume Job Match Checker result.
    $jobSnapshot = [
        'title' => $input['title'] ?? '',
        'description' => $input['description'] ?? '',
    ];

    $lists = [
        ['key' => 'lacking', 'title' => "What's lacking", 'color' => 'amber', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z'],
        ['key' => 'improvements', 'title' => 'How to improve', 'color' => 'indigo', 'icon' => 'M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18'],
    ];
    $colorMap = [
        'amber' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-100', 'dot' => 'text-amber-500'],
        'indigo' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'ring' => 'ring-indigo-100', 'dot' => 'text-indigo-500'],
    ];
@endphp

{{-- Toolbar --}}
<div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">
        Generated {{ optional($this->run->completed_at)->format('M j, Y') }} · this link is yours to keep and share
    </p>
    <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
        <button type="button" x-data @click="$dispatch('open-modal', 'tool-jd')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.1a2.25 2.25 0 01-2.25 2.25h-12A2.25 2.25 0 013.75 18.25v-12A2.25 2.25 0 016 4h4.1M15 3.75h5.25V9m0-5.25L9.75 14.25" />
            </svg>
            Job description
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
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Job description score</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">{{ $input['title'] ?? 'Your job description' }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-ui.badge color="primary">{{ $level }}</x-ui.badge>
                <button type="button" x-data @click="$dispatch('open-modal', 'tool-jd')"
                        class="no-print text-xs font-medium text-indigo-600 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-500">
                    See the description you submitted
                </button>
            </div>
        </div>
        <div class="shrink-0 text-right">
            <div class="text-5xl font-bold tabular-nums text-slate-900">{{ $score }}<span class="text-2xl text-slate-400">/100</span></div>
            <span class="mt-3 inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 {{ $pill }}">{{ $quality }}</span>
        </div>
    </div>
</div>

{{-- Level issue: only when the model reported a mismatch --}}
@if (! empty($jd['level_issue']))
    <div class="print-keep mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-6">
        <div class="flex items-start gap-3">
            <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-amber-100 text-amber-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" /></svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-amber-900">Level mismatch — {{ $level }}</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-amber-800">{{ $jd['level_issue'] }}</p>
            </div>
        </div>
    </div>
@endif

{{-- What's lacking / How to improve --}}
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    @foreach ($lists as $list)
        @php $items = (array) ($jd[$list['key']] ?? []); $c = $colorMap[$list['color']]; @endphp
        @if ($items)
            <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg {{ $c['bg'] }} {{ $c['text'] }} ring-1 {{ $c['ring'] }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $list['icon'] }}" /></svg>
                    </span>
                    <h2 class="text-base font-semibold text-slate-900">{{ $list['title'] }}</h2>
                    <span class="ml-auto text-xs font-medium text-slate-400">{{ count($items) }}</span>
                </div>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($items as $item)
                        <li class="flex gap-2.5 text-sm text-slate-600">
                            <svg class="mt-1 h-1.5 w-1.5 flex-none {{ $c['dot'] }}" viewBox="0 0 6 6" fill="currentColor"><circle cx="3" cy="3" r="3" /></svg>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</div>

{{-- Suggested skills --}}
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    @foreach ([['key' => 'must_have_skills', 'title' => 'Must-have skills', 'color' => 'success'], ['key' => 'soft_skills', 'title' => 'Soft skills', 'color' => 'gray']] as $group)
        @php $skills = (array) ($jd[$group['key']] ?? []); @endphp
        @if ($skills)
            <div class="print-keep rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">{{ $group['title'] }}</h2>
                <p class="mt-1 text-xs text-slate-500">Suggested for this role at {{ $level }} level.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($skills as $skill)
                        <x-ui.badge :color="$group['color']" size="sm">{{ $skill }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- CTA --}}
<div class="no-print mt-8 rounded-2xl bg-gradient-to-br from-indigo-600 to-sky-500 p-6 text-center text-white sm:p-8">
    <h2 class="text-xl font-bold">Now screen the applicants it brings in</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-indigo-100">Hiremium scores every CV against your job automatically — bulk upload, ranked shortlists, per-candidate reports.</p>
    <a href="{{ $this->ctaUrl }}" class="mt-5 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50">Get started free</a>
</div>

{{-- The posting exactly as it was submitted — what every finding above refers to. --}}
<x-ui.modal name="tool-jd" title="Job description (as submitted)" max-width="2xl">
    <x-ui.job-snapshot :snapshot="$jobSnapshot"
                       :note="'This is the job description exactly as it was submitted for this check, analysed against the '.$level.' level. It is stored with the report, so the findings above can always be read against it.'" />
</x-ui.modal>
