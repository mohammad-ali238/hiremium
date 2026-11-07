<x-app-layout :title="'How it works'">

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-white to-slate-50 border-b border-slate-200">
        <div class="absolute inset-0 -z-10">
            <div class="absolute -top-32 -right-32 h-96 w-96 rounded-full bg-indigo-200/40 blur-3xl"></div>
        </div>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold ring-1 ring-indigo-100">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                How Hiremium works
            </span>
            <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">
                From a pile of CVs to a ranked shortlist
            </h1>
            <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                Post a job, upload your applicants, and let Hiremium read, analyze and score each one.
                You come back to a ranked list of candidates — every one with its own report.
            </p>
            <div class="mt-10 max-w-3xl mx-auto">
                <x-site.workflow-illustration variant="pipeline" class="w-full drop-shadow-sm" />
            </div>
        </div>
    </section>

    {{-- Steps --}}
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @php
                $steps = [
                    [
                        'n' => '01', 'title' => 'Post a job with its requirements',
                        'body' => 'Create a job and describe what you are hiring for — the role, the responsibilities, and the skills that matter. This is what every candidate is measured against, so the clearer the job, the sharper the matching.',
                        'icon' => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z',
                    ],
                    [
                        'n' => '02', 'title' => 'Upload CVs in bulk',
                        'body' => 'Add resumes together, or drop a single ZIP containing the whole batch. Common formats work — PDF, Word and plain text — including scanned or image-based CVs, which are read automatically. A big batch is split up and processed for you.',
                        'icon' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
                    ],
                    [
                        'n' => '03', 'title' => 'AI reads and parses each CV',
                        'body' => 'Every resume is turned into a clean, structured profile — contact details, experience, education, projects, skills and languages — so nothing important is buried in formatting or scanned pages.',
                        'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zM9 12.75h6m-6 3h6',
                    ],
                    [
                        'n' => '04', 'title' => 'Each candidate is analyzed and scored',
                        'body' => 'Hiremium compares each candidate against your job — one by one — and gives them a match score, along with the reasons to hire, the concerns to weigh, and which required skills they have and are missing.',
                        'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    ],
                    [
                        'n' => '05', 'title' => 'Results rank themselves — top scorers first',
                        'body' => 'As each result lands, your list re-orders so the strongest matches rise to the top. You never scroll through raw uploads; you look at the best candidates first.',
                        'icon' => 'M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0l-3.75-3.75M17.25 21L21 17.25',
                    ],
                    [
                        'n' => '06', 'title' => 'Open a report for any candidate',
                        'body' => 'Click a candidate to see their full report: the match score, the reasons to hire and the concerns, matched and missing skills, and their key details — everything you need to decide, on one screen.',
                        'icon' => 'M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75',
                    ],
                    [
                        'n' => '07', 'title' => 'Shortlist, compare and export',
                        'body' => 'Shortlist the candidates you like, compare your top picks side by side, and export the results — ready to share with your team and move to interviews.',
                        'icon' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
                    ],
                ];
            @endphp

            <ol class="space-y-6">
                @foreach ($steps as $s)
                    <li class="flex gap-5 rounded-2xl ring-1 ring-slate-200 bg-white p-6 sm:p-8 hover:shadow-sm transition">
                        <div class="shrink-0">
                            <div class="relative h-14 w-14 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/>
                                </svg>
                                <span class="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center ring-2 ring-white">{{ $loop->iteration }}</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $s['title'] }}</h3>
                            <p class="mt-2 text-slate-600 leading-relaxed">{{ $s['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 bg-slate-50 border-t border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-12 sm:px-12 sm:py-16 text-center text-white shadow-xl">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Ready to screen your next batch?</h2>
                <p class="mt-3 text-indigo-100 max-w-2xl mx-auto">
                    Create an account, post a job, and upload your applicants — your ranked shortlist is minutes away.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-md bg-white text-slate-900 hover:bg-slate-100 px-6 py-3 text-base font-semibold w-full sm:w-auto">
                        Get started
                    </a>
                    <a href="{{ route('pricing') }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-700/40 hover:bg-indigo-700/60 ring-1 ring-white/20 px-6 py-3 text-base font-semibold text-white w-full sm:w-auto">
                        See pricing
                    </a>
                </div>
            </div>
        </div>
    </section>

</x-app-layout>
