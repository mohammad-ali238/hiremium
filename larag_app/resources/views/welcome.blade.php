<x-app-layout>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-white to-slate-50">
        <div class="absolute inset-0 -z-10">
            <div class="absolute -top-32 -right-32 h-96 w-96 rounded-full bg-indigo-200/40 blur-3xl"></div>
            <div class="absolute top-40 -left-32 h-96 w-96 rounded-full bg-emerald-200/30 blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16 lg:grid lg:grid-cols-2 lg:gap-12 lg:items-center">
            {{-- Copy --}}
            <div class="text-center lg:text-left">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold ring-1 ring-indigo-100">
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                    Automated recruitment, powered by AI
                </span>

                <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900">
                    Upload CVs in bulk.<br class="hidden sm:block" />
                    Get your best-matched candidates in minutes.
                </h1>

                <p class="mt-5 text-xl font-semibold text-indigo-600">Smarter Hiring. Powered by AI.</p>

                <p class="mt-4 max-w-xl mx-auto lg:mx-0 text-lg text-slate-600">
                    Drop in a pile of resumes and let Hiremium do the screening. Every CV is read,
                    analyzed and scored against your job — then your candidates arrive ranked from
                    the strongest match down, each with its own report. Hours of manual shortlisting,
                    done automatically.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-600 hover:bg-indigo-700 px-6 py-3 text-base font-semibold text-white shadow-sm transition w-full sm:w-auto">
                        Get started
                    </a>
                    <a href="{{ route('how-it-works') }}"
                       class="inline-flex items-center justify-center rounded-md bg-white hover:bg-slate-100 px-6 py-3 text-base font-semibold text-slate-900 ring-1 ring-slate-200 transition w-full sm:w-auto">
                        See how it works
                    </a>
                </div>

                <p class="mt-4 text-xs text-slate-500">No manual screening. Ranked shortlists and a report for every candidate.</p>
            </div>

            {{-- Hero illustration: bulk upload → analyze → ranked scores --}}
            <div class="mt-14 lg:mt-0">
                <x-site.workflow-illustration variant="hero" class="w-full max-w-lg mx-auto drop-shadow-xl" />
            </div>
        </div>
    </section>

    {{-- Value props --}}
    <section class="py-20 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">
                    Screening that runs itself
                </h2>
                <p class="mt-4 text-slate-600">
                    Post a job, upload the applicants, and let the analysis run. You come back to a ranked list.
                </p>
            </div>

            <div class="mt-14 grid grid-cols-1 md:grid-cols-3 gap-6">
                @php
                    $props = [
                        [
                            'title' => 'Bulk upload, automatic scoring',
                            'body'  => 'Add resumes one by one or drop a ZIP of the whole batch. Every CV is read and scored against your job — no copy-pasting, no reading each one yourself.',
                            'icon'  => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
                        ],
                        [
                            'title' => 'Save hours on every role',
                            'body'  => 'What takes a recruiter an afternoon of manual shortlisting happens automatically in minutes, so your team spends its time interviewing — not sifting.',
                            'icon'  => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                        ],
                        [
                            'title' => 'A report for every candidate',
                            'body'  => 'Each candidate gets a match score with the reasons to hire, the concerns, and which required skills they have and are missing — ready to review at a glance.',
                            'icon'  => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zM9 12.75h6m-6 3h6',
                        ],
                    ];
                @endphp
                @foreach ($props as $p)
                    <div class="rounded-xl border border-slate-200 bg-white p-6 hover:border-indigo-200 hover:shadow-sm transition">
                        <div class="h-11 w-11 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $p['icon'] }}"/>
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $p['title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $p['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works teaser --}}
    <section class="py-20 bg-slate-50 border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">
                    From a pile of CVs to a ranked shortlist
                </h2>
                <p class="mt-4 text-slate-600">Four steps, and the analysis runs on its own.</p>
            </div>

            <div class="mt-12 max-w-3xl mx-auto">
                <x-site.workflow-illustration variant="pipeline" class="w-full drop-shadow-sm" />
            </div>

            <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $steps = [
                        ['n' => '01', 'title' => 'Upload CVs in bulk',      'body' => 'Add resumes as files, or drop a single ZIP with the whole batch.'],
                        ['n' => '02', 'title' => 'AI reads each CV',        'body' => 'Every resume is parsed and analyzed against your job — one by one.'],
                        ['n' => '03', 'title' => 'Match scores assigned',   'body' => 'Each candidate gets a match score with clear reasons behind it.'],
                        ['n' => '04', 'title' => 'Top scorers first',       'body' => 'Results rank automatically so the strongest matches sit at the top.'],
                    ];
                @endphp
                @foreach ($steps as $s)
                    <div class="relative rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <span class="text-3xl font-extrabold text-indigo-600">{{ $s['n'] }}</span>
                        <h3 class="mt-3 text-base font-semibold text-slate-900">{{ $s['title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $s['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('how-it-works') }}"
                   class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                    See the full workflow
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="py-20 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Simple plans, AI usage included</h2>
                <p class="mt-4 text-slate-600">Pick the AI usage that fits your hiring volume. Every plan includes the full workflow.</p>
            </div>

            <div class="mt-14">
                <x-site.pricing-grid
                    :plans="$plans"
                    :current-subscription="$currentSubscription ?? null"
                    :billing-enabled="$billingEnabled ?? false" />
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('pricing') }}"
                   class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                    See full pricing
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-20 bg-slate-50 border-t border-slate-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 text-center">Frequently asked questions</h2>
            <div class="mt-10 space-y-4">
                @php
                    $faqs = [
                        ['q' => 'How does Hiremium match candidates to a job?', 'a' => 'You post a job with its requirements, then upload the applicants’ CVs. Hiremium reads each resume, compares it against the job, and gives every candidate a match score with the reasons to hire, the concerns, and which required skills they have or are missing.'],
                        ['q' => 'Can I upload many CVs at once?', 'a' => 'Yes — that’s the point. Add resumes together, or drop a single ZIP containing the whole batch, and they’re all analyzed automatically. Results rank themselves so the strongest matches sit at the top.'],
                        ['q' => 'Which AI powers Hiremium?', 'a' => 'Hiremium runs on leading large language models from OpenAI and Google Gemini behind the scenes. We manage the models, the prompts and the scoring for you — there’s nothing to set up on your side.'],
                        ['q' => 'What file types can I upload?', 'a' => 'Common resume formats are supported — PDF, Word documents and plain text — including scanned or image-based CVs, which are read automatically.'],
                        ['q' => 'What is AI usage?', 'a' => 'Your plan includes a monthly amount of AI usage, measured in Requests. Analyzing one CV against a job uses one AI Request; browsing your ranked shortlists does not. If a request fails on our side, it’s refunded automatically.'],
                        ['q' => 'Is my candidate data kept private?', 'a' => 'Yes. Uploads are stored privately and served only through your account. We do not use your submitted content to train models. See our Privacy Policy for the full picture.'],
                    ];
                @endphp
                @foreach ($faqs as $faq)
                    <details class="group rounded-xl bg-white ring-1 ring-slate-200 px-5 py-4 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer text-slate-900 font-medium">
                            {{ $faq['q'] }}
                            <svg class="h-5 w-5 text-slate-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 bg-slate-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-12 sm:px-12 sm:py-16 text-center text-white shadow-xl">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">
                    Let AI screen your next stack of CVs
                </h2>
                <p class="mt-3 text-indigo-100 max-w-2xl mx-auto">
                    Post a job, upload the applicants, and come back to a ranked shortlist with a report for every candidate.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-md bg-white text-slate-900 hover:bg-slate-100 px-6 py-3 text-base font-semibold w-full sm:w-auto">
                        Create account
                    </a>
                    <a href="{{ route('how-it-works') }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-700/40 hover:bg-indigo-700/60 ring-1 ring-white/20 px-6 py-3 text-base font-semibold text-white w-full sm:w-auto">
                        See how it works
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Contact — the same form and behaviour as the contact page. --}}
    <section id="contact" class="py-20 bg-white border-t border-slate-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">Questions? Talk to a human</h2>
                <p class="mt-3 text-lg text-slate-600">
                    Pre-sales question, custom plan, integration help — drop us a line and we'll get back within one business day.
                </p>
            </div>
            <div class="mt-10 max-w-3xl mx-auto">
                <x-site.contact-form :topics="$topics" :recaptcha-enabled="! empty($recaptchaEnabled)" :recaptcha-site-key="$recaptchaSiteKey ?? ''" id="hiremium-home-contact-form" />
            </div>
        </div>
    </section>

</x-app-layout>
