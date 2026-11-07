<x-app-layout :title="'AI Match Scoring'">

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 text-white">
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
            <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-28 -right-20 h-96 w-96 rounded-full bg-violet-400/20 blur-3xl"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-indigo-100 text-xs font-semibold ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    AI Match Scoring
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                    Every candidate, scored and explained.
                </h1>
                <p class="mt-5 text-lg text-indigo-100 leading-relaxed">
                    Upload your applicants and each one is measured against the job — with a clear
                    match score and the reasons behind it. Your strongest candidates rise to the top
                    before anyone reads a single CV by hand.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-md bg-white text-slate-900 hover:bg-slate-100 px-6 py-3 text-base font-semibold shadow-sm transition">
                        Get started
                    </a>
                    <a href="{{ route('how-it-works') }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-700/40 hover:bg-indigo-700/60 ring-1 ring-white/20 px-6 py-3 text-base font-semibold text-white transition">
                        See how it works
                    </a>
                </div>
            </div>
            <div class="rounded-2xl bg-white/5 ring-1 ring-white/15 p-6 backdrop-blur-sm">
                <x-site.workflow-illustration variant="hero" class="w-full max-w-md mx-auto" />
            </div>
        </div>
    </section>

    {{-- Feature blocks --}}
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

            @php
                $blocks = [
                    [
                        'eyebrow' => 'Scoring',
                        'title' => 'A consistent rubric, not a gut feeling',
                        'body'  => 'Every candidate is weighed the same way — required-skills coverage, job-relevant experience, education, nice-to-have skills, and how their CV reads on communication and motivation — each with a fixed weight, and always with a written reason. No two reviewers drifting on different standards; one clear score you can trust.',
                        'bullets' => ['Fixed weights, applied to every candidate alike', 'A written reason with every score', 'Scored against your job’s actual requirements', 'See the points each part of the score earned'],
                        'panel' => 'score',
                    ],
                    [
                        'eyebrow' => 'Explainability',
                        'title' => 'Reasons to hire, and the concerns',
                        'body'  => 'Each candidate’s report spells out what makes them a strong fit and what to watch for — so you are not staring at a number, you are reading a shortlist you can defend to your team.',
                        'bullets' => ['Clear reasons to move a candidate forward', 'Honest concerns worth weighing', 'Written in plain, recruiter-friendly language', 'Ready to share when you shortlist'],
                        'panel' => 'reasons',
                    ],
                    [
                        'eyebrow' => 'Skills',
                        'title' => 'Required skills, matched and missing',
                        'body'  => 'Mark the skills a role needs and every candidate’s report shows which they have and which they are missing at a glance — the fastest way to spot who truly fits.',
                        'bullets' => ['Flag the must-have skills on the job', 'See matched and missing skills per candidate', 'Spot gaps before the interview', 'No re-reading CVs to check requirements'],
                        'panel' => 'skills',
                    ],
                    [
                        'eyebrow' => 'Ranking',
                        'title' => 'Top scorers, automatically first',
                        'body'  => 'As results land, your candidate list re-orders itself so the strongest matches sit at the top. You spend your time on the best people, not on sorting.',
                        'bullets' => ['Ranked from strongest match down', 'Updates live as candidates finish', 'Shortlist and compare your top picks', 'Export when you’re ready to share'],
                        'panel' => 'rank',
                    ],
                ];
            @endphp

            @foreach ($blocks as $i => $block)
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div @class(['order-2' => $i % 2, 'order-1' => ! ($i % 2)])>
                        <span class="inline-block text-xs font-semibold uppercase tracking-wider text-indigo-600">{{ $block['eyebrow'] }}</span>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $block['title'] }}</h2>
                        <p class="mt-4 text-slate-600 leading-relaxed">{{ $block['body'] }}</p>
                        <ul class="mt-6 space-y-2">
                            @foreach ($block['bullets'] as $b)
                                <li class="flex gap-2 text-sm text-slate-700">
                                    <span class="text-emerald-500">&#10003;</span>
                                    {{ $b }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div @class(['order-1 lg:order-2' => $i % 2, 'order-2 lg:order-1' => ! ($i % 2)])>
                        <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 shadow-lg">
                            @if ($block['panel'] === 'score')
                                <div class="space-y-4">
                                    @foreach ([['Ana R.', 91, '#6366f1'], ['Sam K.', 87, '#0ea5e9'], ['Lee M.', 74, '#f59e0b']] as [$name, $score, $c])
                                        <div class="flex items-center gap-3">
                                            <span class="h-9 w-9 rounded-full shrink-0" style="background: {{ $c }}; opacity:.9"></span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-semibold text-slate-800">{{ $name }}</div>
                                                <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-emerald-500" style="width: {{ $score }}%"></div>
                                                </div>
                                            </div>
                                            <span class="text-lg font-extrabold text-slate-900 tabular-nums">{{ $score }}</span>
                                        </div>
                                    @endforeach
                                    <p class="text-xs text-slate-500 leading-relaxed pt-1">“Strong backend experience and a clear match on the core stack; some gaps in team leadership.”</p>
                                </div>
                            @elseif ($block['panel'] === 'reasons')
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Reasons to hire</div>
                                        <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                            <li class="flex gap-2"><span class="text-emerald-500">&#10003;</span> Deep domain experience</li>
                                            <li class="flex gap-2"><span class="text-emerald-500">&#10003;</span> Core skills covered</li>
                                            <li class="flex gap-2"><span class="text-emerald-500">&#10003;</span> Clear career progression</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Concerns</div>
                                        <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                            <li class="flex gap-2"><span class="text-amber-500">!</span> Short tenure recently</li>
                                            <li class="flex gap-2"><span class="text-amber-500">!</span> Limited leadership</li>
                                        </ul>
                                    </div>
                                </div>
                            @elseif ($block['panel'] === 'skills')
                                <div class="space-y-4">
                                    <div>
                                        <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Matched skills</div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (['PHP', 'Laravel', 'MySQL', 'Redis', 'REST APIs'] as $sk)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-3 py-1 text-xs font-medium">{{ $sk }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Missing skills</div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (['Kubernetes', 'Team leadership'] as $sk)
                                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 ring-1 ring-slate-200 px-3 py-1 text-xs font-medium line-through">{{ $sk }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                <x-site.workflow-illustration variant="pipeline" class="w-full" />
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </section>

    {{-- Cross-link + CTA --}}
    <section class="py-20 bg-slate-50 border-t border-slate-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">Put scoring to work on your next batch</h2>
            <p class="mt-3 text-slate-600">
                Post a job, upload your applicants, and come back to a ranked shortlist. Every CV is read
                and structured first by
                <a href="{{ route('features.parsing') }}" class="font-medium text-indigo-600 hover:text-indigo-800 underline">AI Resume Parsing</a>.
            </p>
            <a href="{{ route('register') }}"
               class="mt-8 inline-flex items-center justify-center rounded-md bg-indigo-600 hover:bg-indigo-700 px-6 py-3 text-base font-semibold text-white shadow-sm transition">
                Get started
            </a>
        </div>
    </section>

</x-app-layout>
