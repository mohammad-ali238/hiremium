<x-app-layout :title="'About'">

    <section class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">
                Built for the boring, valuable parts of hiring
            </h1>
            <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                We don't try to replace recruiters — we just make the first-pass triage faster and cheaper.
            </p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-slate">
            <h2 class="text-2xl font-bold text-slate-900">Why this exists</h2>
            <p class="text-slate-600 leading-relaxed">
                Most job boards still rely on humans to read every cover letter, even when 70% of the
                applicant pool is obviously off-target. AI is good enough now to do that first pass —
                and the cost-per-call has dropped to where it makes sense to run on every application.
            </p>
            <p class="text-slate-600 leading-relaxed mt-4">
                We built Hiremium to do exactly that: post a job, upload your applicants’ CVs in bulk,
                and get a ranked shortlist back — every candidate with a match score and a report of the
                reasons to hire, the concerns, and the skills they have and are missing. No opaque scoring,
                no afternoon lost to reading resumes.
            </p>

            <h2 class="text-2xl font-bold text-slate-900 mt-12">What we believe</h2>
            <ul class="space-y-3 text-slate-600">
                <li class="flex gap-3"><span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-indigo-600 flex-shrink-0"></span> Scoring should be explainable. Every candidate comes with the reasons behind their match — never just a number.</li>
                <li class="flex gap-3"><span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-indigo-600 flex-shrink-0"></span> Pricing should match usage. Pay for AI usage, not seats, and failed requests are refunded automatically.</li>
                <li class="flex gap-3"><span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-indigo-600 flex-shrink-0"></span> One tool should cover the first pass. Upload the whole batch, and reading, scoring and ranking happen together.</li>
            </ul>
        </div>
    </section>

    <section class="py-16 bg-slate-50 border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                @foreach ([
                    ['k' => '0-100', 'v' => 'Match score range'],
                    ['k' => '~2s',   'v' => 'Typical end-to-end latency'],
                    ['k' => '60/min','v' => 'Default rate limit per key'],
                    ['k' => '100%',  'v' => 'Refund on AI failure'],
                ] as $stat)
                    <div class="bg-white rounded-xl ring-1 ring-slate-200 p-6">
                        <div class="text-3xl font-extrabold text-indigo-600">{{ $stat['k'] }}</div>
                        <div class="mt-1 text-sm text-slate-600">{{ $stat['v'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</x-app-layout>
