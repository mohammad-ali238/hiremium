<x-app-layout :title="'AI Resume Parsing'">

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-violet-700 via-indigo-700 to-indigo-800 text-white">
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
            <div class="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-28 -left-20 h-96 w-96 rounded-full bg-violet-400/20 blur-3xl"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-indigo-100 text-xs font-semibold ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    AI Resume Parsing
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                    Any CV in. A clean candidate profile out.
                </h1>
                <p class="mt-5 text-lg text-indigo-100 leading-relaxed">
                    Every resume you upload — a polished PDF, an old Word file, plain text, or a photo
                    of a printed CV — is read and turned into a complete, tidy profile: contact details,
                    experience, education, projects, skills and languages. So the AI can score what
                    actually matters, not fight with formatting.
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
                <x-site.workflow-illustration variant="pipeline" tone="dark" class="w-full" />
            </div>
        </div>
    </section>

    {{-- Feature blocks --}}
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

            @php
                $blocks = [
                    [
                        'eyebrow' => 'Clean profiles',
                        'title' => 'One upload, one complete profile',
                        'body'  => 'Every CV becomes a tidy candidate profile — contact details, work history, education, projects, skills and languages — pulled out of whatever layout the resume happened to use. Nothing is invented: details the CV doesn’t mention simply come back empty.',
                        'bullets' => ['Contact details, experience and education', 'Projects, certificates and awards', 'Skills and spoken languages, kept separate', 'Faithful to the CV — no guessing'],
                        'panel' => 'profile',
                    ],
                    [
                        'eyebrow' => 'Any format',
                        'title' => 'Designed, scanned, or ancient — it still reads',
                        'body'  => 'Typed documents are read directly; scanned PDFs and photographed CVs are read visually, so a picture of a printed resume parses just as cleanly as a fresh export. You don’t have to sort your files first — just upload the batch.',
                        'bullets' => ['PDF, Word and plain text', 'Scanned pages and photo CVs', 'Handled automatically, per file', 'No manual clean-up before uploading'],
                        'panel' => 'formats',
                    ],
                    [
                        'eyebrow' => 'Depth',
                        'title' => 'Deep detail, not just name and email',
                        'body'  => 'The full work history, the projects buried at the bottom of the CV, and the skills and languages a candidate lists — all captured, so scoring has the whole picture to work with instead of a headline.',
                        'bullets' => ['Complete experience and education history', 'Projects, certificates and awards', 'Expertise areas with proficiency', 'Spoken languages separated from skills'],
                        'panel' => 'depth',
                    ],
                    [
                        'eyebrow' => 'Built for volume',
                        'title' => 'Made for the whole inbox',
                        'body'  => 'Parsing runs across your entire batch automatically — the profiles feed straight into match scoring, so uploading a pile of CVs turns into a ranked shortlist without any middle step.',
                        'bullets' => ['Runs across a whole batch', 'Feeds straight into scoring', 'Nothing to configure per file', 'Uploads stored privately'],
                        'panel' => 'volume',
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
                            @if ($block['panel'] === 'formats')
                                <div class="flex flex-wrap gap-2">
                                    @foreach (['PDF', 'DOCX', 'DOC', 'TXT', 'Scanned PDF', 'Photo (JPG / PNG)'] as $fmt)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100 px-3 py-2 text-sm font-medium">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                            {{ $fmt }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <dl class="space-y-3 text-sm">
                                    @foreach ([['Name', 'Jane Doe'], ['Title', 'Senior PHP Engineer'], ['Experience', '8 years · 3 roles'], ['Education', 'B.Sc. Computer Science'], ['Skills', 'PHP · Laravel · Redis · MySQL'], ['Languages', 'English · Spanish']] as [$k, $v])
                                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-2 last:border-0">
                                            <dt class="text-slate-500">{{ $k }}</dt>
                                            <dd class="text-slate-800 font-medium text-right">{{ $v }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
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
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">Turn your CV pile into a shortlist</h2>
            <p class="mt-3 text-slate-600">
                Every parsed candidate is scored and ranked against your job by
                <a href="{{ route('features.matching') }}" class="font-medium text-indigo-600 hover:text-indigo-800 underline">AI Match Scoring</a>.
            </p>
            <a href="{{ route('register') }}"
               class="mt-8 inline-flex items-center justify-center rounded-md bg-indigo-600 hover:bg-indigo-700 px-6 py-3 text-base font-semibold text-white shadow-sm transition">
                Get started
            </a>
        </div>
    </section>

</x-app-layout>
