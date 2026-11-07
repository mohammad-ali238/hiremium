@php
    $ats = (array) ($report['ats_result'] ?? []);
    $sub = (array) ($ats['sub_scores'] ?? []);
    $roles = (array) ($ats['target_roles'] ?? []);
    $context = array_filter([$ats['industry'] ?? null, $ats['specialization'] ?? null, $ats['seniority'] ?? null]);
    $subLabels = ['ats' => 'ATS parse-ability', 'summary' => 'Summary', 'experience' => 'Experience', 'skills' => 'Skills'];
    $lists = [
        ['key' => 'critical_issues', 'title' => 'Critical issues', 'color' => 'rose', 'icon' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z'],
        ['key' => 'strengths', 'title' => 'Strengths', 'color' => 'emerald', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['key' => 'weaknesses', 'title' => 'What\'s lacking', 'color' => 'amber', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z'],
        ['key' => 'suggestions', 'title' => 'How to improve', 'color' => 'indigo', 'icon' => 'M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18'],
    ];
    $colorMap = [
        'rose' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'ring' => 'ring-rose-100', 'dot' => 'text-rose-500'],
        'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-100', 'dot' => 'text-emerald-500'],
        'amber' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-100', 'dot' => 'text-amber-500'],
        'indigo' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'ring' => 'ring-indigo-100', 'dot' => 'text-indigo-500'],
    ];
@endphp

{{-- Toolbar (no job modal — ATS scores the resume alone) --}}
<div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">
        Generated {{ optional($this->run->completed_at)->format('M j, Y') }} · this link is yours to keep and share
    </p>
    <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
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

{{-- Headline: the ATS score --}}
<div class="print-keep print-plain rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">ATS resume score</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">How your resume reads to an ATS</h1>
            @if ($context)
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($context as $chip)
                        <x-ui.badge color="gray">{{ $chip }}</x-ui.badge>
                    @endforeach
                </div>
            @endif
            @if ($roles)
                <p class="mt-3 text-sm text-slate-500">Best-fit roles:
                    <span class="font-medium text-slate-700">{{ implode(' · ', $roles) }}</span>
                </p>
            @endif
        </div>
        <div class="shrink-0 text-right">
            <div class="text-5xl font-bold tabular-nums text-slate-900">{{ $ats['score'] ?? '—' }}<span class="text-2xl text-slate-400">/100</span></div>
            <x-ui.score-pill :score="$ats['score'] ?? 0" class="mt-3" />
        </div>
    </div>

    {{-- Sub-scores --}}
    @if ($sub)
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($subLabels as $key => $label)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $sub[$key] ?? 0 }}<span class="text-sm text-slate-400">/100</span></p>
                    <x-ui.progress :value="$sub[$key] ?? 0"
                                   :color="($sub[$key] ?? 0) >= 75 ? 'success' : (($sub[$key] ?? 0) >= 60 ? 'warning' : 'danger')"
                                   class="mt-2" />
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Feedback lists --}}
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    @foreach ($lists as $list)
        @php $items = (array) ($ats[$list['key']] ?? []); $c = $colorMap[$list['color']]; @endphp
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
                            <span>{{ is_array($item) ? ($item['text'] ?? '') : $item }}@if (is_array($item) && ! empty($item['section']) && $item['section'] !== 'general')<span class="ml-1 text-xs text-slate-400">· {{ ucfirst($item['section']) }}</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</div>

{{-- CTA --}}
<div class="no-print mt-8 rounded-2xl bg-gradient-to-br from-indigo-600 to-sky-500 p-6 text-center text-white sm:p-8">
    <h2 class="text-xl font-bold">Screening candidates, not just your own CV?</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-indigo-100">Hiremium scores every applicant against your job automatically — bulk upload, ranked shortlists, per-candidate reports.</p>
    <a href="{{ $this->ctaUrl }}" class="mt-5 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50">Get started free</a>
</div>
