<x-app-layout :title="'Help Center'">

@php
    $nav = [
        'Getting started' => [
            ['id' => 'overview',    'label' => 'Overview'],
            ['id' => 'account',     'label' => 'Create an account'],
            ['id' => 'dashboard',   'label' => 'Your dashboard'],
        ],
        'Using your dashboard' => [
            ['id' => 'post-job',    'label' => 'Post a job'],
            ['id' => 'upload',      'label' => 'Upload CVs in bulk'],
            ['id' => 'analyzing',   'label' => 'How analysis works'],
            ['id' => 'candidates',  'label' => 'Candidates & scores'],
            ['id' => 'report',      'label' => 'The candidate report'],
            ['id' => 'shortlists',  'label' => 'Shortlists & compare'],
            ['id' => 'reports',     'label' => 'Reports & export'],
            ['id' => 'team',        'label' => 'Team & seats'],
            ['id' => 'settings',    'label' => 'Settings'],
        ],
        'AI usage & billing' => [
            ['id' => 'ai-usage',     'label' => 'How AI usage works'],
            ['id' => 'plans',        'label' => 'Plans & billing cycles'],
            ['id' => 'upgrade',      'label' => 'Upgrading your plan'],
            ['id' => 'cancel',       'label' => 'Cancelling'],
            ['id' => 'refunds',      'label' => 'Refund policy'],
        ],
        'For developers (API)' => [
            ['id' => 'api-overview',   'label' => 'API overview'],
            ['id' => 'api-key',        'label' => 'Generate an API key'],
            ['id' => 'authentication', 'label' => 'Authentication'],
            ['id' => 'endpoints',      'label' => 'Endpoints'],
            ['id' => 'async',          'label' => 'Async jobs'],
        ],
        'Troubleshooting' => [
            ['id' => 'errors',        'label' => 'Common errors'],
            ['id' => 'failed-calls',  'label' => 'Failed requests & refunds'],
            ['id' => 'data-security', 'label' => 'Data & security'],
        ],
        'Support' => [
            ['id' => 'support', 'label' => 'Still need help?'],
        ],
    ];

    $ico = 'rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono';
@endphp

    {{-- Header --}}
    <section class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold ring-1 ring-indigo-100">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                Help Center
            </span>
            <h1 class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">How can we help?</h1>
            <p class="mt-3 max-w-2xl text-slate-600">
                Guides for getting the most out of {{ config('app.name') }} — your dashboard workflow, AI usage,
                billing, the developer API, and troubleshooting. Pick a topic on the right, or read straight through.
            </p>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:grid lg:grid-cols-[minmax(0,1fr)_16rem] lg:gap-12">

        {{-- Mobile topic picker --}}
        <details class="lg:hidden mb-8 rounded-xl ring-1 ring-slate-200 bg-white [&_summary::-webkit-details-marker]:hidden">
            <summary class="flex items-center justify-between cursor-pointer px-4 py-3 text-sm font-semibold text-slate-900">
                Jump to a topic
                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="border-t border-slate-200 px-4 py-3 grid grid-cols-2 gap-1">
                @foreach ($nav as $items)
                    @foreach ($items as $item)
                        <a href="#{{ $item['id'] }}" class="py-1.5 text-sm text-slate-600 hover:text-indigo-600">{{ $item['label'] }}</a>
                    @endforeach
                @endforeach
            </div>
        </details>

        {{-- Content (left) — timeline --}}
        <div class="min-w-0 order-1">
            <div class="relative">
                <span class="hidden sm:block absolute left-[7px] top-4 bottom-4 w-px bg-slate-200" aria-hidden="true"></span>

                @php
                    $sections = [
                        ['id' => 'overview', 'h' => 'Overview', 'html' => '
                            <p>'.config('app.name').' is an automated recruitment tool: post a job, <strong>upload your applicants&rsquo; CVs in bulk</strong>, and let the AI read, analyze and score each one against the job. You come back to a <strong>ranked shortlist</strong> — strongest matches first — with a report for every candidate.</p>
                            <p>New here? The fastest path: create an account, post a job, and upload some CVs from your <a href="'.route('app.dashboard').'">dashboard</a>. Prefer to integrate it into your own product? There is also a <a href="'.route('docs').'">developer API</a>.</p>
                        '],
                        ['id' => 'account', 'h' => 'Create an account', 'html' => '
                            <p>Sign up with your email (or a social login) from the <a href="'.route('register').'">registration page</a>. You will need to <strong>verify your email</strong> before you can start — this keeps accounts secure.</p>
                        '],
                        ['id' => 'dashboard', 'h' => 'Your dashboard', 'html' => '
                            <p>Your <a href="'.route('app.dashboard').'">dashboard</a> is where everything happens: your jobs, your uploads, your ranked candidates, and your remaining AI usage. From here you post jobs, upload CVs, review shortlists, and manage your plan and team.</p>
                        '],

                        ['id' => 'post-job', 'h' => 'Post a job', 'html' => '
                            <p>Create a job from your dashboard and describe the role, the responsibilities, and the skills that matter. Every candidate is scored against this job, so the clearer the description, the sharper the matching. You can mark certain skills as required so the report shows who has them and who is missing them.</p>
                        '],
                        ['id' => 'upload', 'h' => 'Upload CVs in bulk', 'html' => '
                            <p>Open the <strong>Upload</strong> page, choose the job, and add your applicants. You can add resumes together, or drop a single <strong>ZIP</strong> containing the whole batch — a large batch is split up and processed for you. Common formats work — PDF, Word and plain text — including scanned or image-based CVs, which are read automatically.</p>
                            <p>Already have candidate details in a spreadsheet? Use the <strong>CSV import</strong> tab, map your columns to the candidate fields, and import the rows straight in.</p>
                        '],
                        ['id' => 'analyzing', 'h' => 'How analysis works', 'html' => '
                            <p>Each CV is read and turned into a clean, structured profile — contact details, experience, education, projects, skills and languages — then <strong>analyzed and scored against your job, one by one</strong>. You can watch progress on the batch page; results appear as each candidate finishes, and the list keeps re-ordering so the best matches stay on top.</p>
                        '],
                        ['id' => 'candidates', 'h' => 'Candidates & scores', 'html' => '
                            <p>Every candidate gets a <strong>match score</strong> that reflects how well they fit the job. Your candidate list ranks automatically — <strong>top scorers first</strong> — so you review the strongest matches before anything else, instead of scrolling through raw uploads.</p>
                        '],
                        ['id' => 'report', 'h' => 'The candidate report', 'html' => '
                            <p>Open any candidate to see their full report: the match score, the <strong>reasons to hire</strong>, the <strong>concerns</strong> to weigh, and which <strong>required skills</strong> they have and are missing — plus their key details. Everything you need to make a call sits on one screen.</p>
                        '],
                        ['id' => 'shortlists', 'h' => 'Shortlists & compare', 'html' => '
                            <p>Shortlist the candidates you like to set them aside, and <strong>compare</strong> your top picks side by side to weigh them against each other before moving to interviews.</p>
                        '],
                        ['id' => 'reports', 'h' => 'Reports & export', 'html' => '
                            <p>Each job has a screening report summarising your candidates and results. When you are ready to share with your team, <strong>export</strong> your matched candidates and shortlists — the export includes their key details and scores.</p>
                        '],
                        ['id' => 'team', 'h' => 'Team & seats', 'html' => '
                            <p>Invite colleagues into your workspace so they can review candidates alongside you, up to the number of seats your plan includes. Manage members and invites from the <strong>Team</strong> page.</p>
                        '],
                        ['id' => 'settings', 'h' => 'Settings', 'html' => '
                            <p>Manage your profile, your workspace, and your notification preferences from <strong>Settings</strong>. Your plan, AI usage and billing history live under <strong>Billing</strong>.</p>
                        '],

                        ['id' => 'ai-usage', 'h' => 'How AI usage works', 'html' => '
                            <p>Your plan includes a monthly amount of <strong>AI usage</strong>, measured in Requests. <strong>Analyzing one CV against a job uses one AI Request</strong>; uploading files and browsing your ranked shortlists do not. AI usage resets when your subscription renews and <strong>does not roll over</strong>. If a request fails on our side, it is <strong>refunded automatically</strong> — you only spend AI usage on results you actually get.</p>
                        '],
                        ['id' => 'plans', 'h' => 'Plans & billing cycles', 'html' => '
                            <p>Plans are AI-usage allowances billed <strong>monthly or yearly</strong> and renew automatically. Yearly plans include a discounted annual allowance granted up front. Compare options on the <a href="'.route('pricing').'">pricing page</a>. Payments are securely handled by our third-party payment provider.</p>
                        '],
                        ['id' => 'upgrade', 'h' => 'Upgrading your plan', 'html' => '
                            <p>On the pricing page, choose a higher plan (or switch your current plan to yearly). Upgrading starts a new checkout; once it completes, your previous subscription is superseded so you are never billed twice, and your new AI usage becomes available. You cannot switch to the same or a lower plan while a subscription is active.</p>
                        '],
                        ['id' => 'cancel', 'h' => 'Cancelling', 'html' => '
                            <p>Cancel any time from the receipt or customer-portal link in your payment confirmation email. Your access and remaining AI usage continue until the <strong>end of the current paid period</strong>; the subscription simply does not renew after that.</p>
                        '],
                        ['id' => 'refunds', 'h' => 'Refund policy', 'html' => '
                            <p><strong>We do not offer refunds</strong> for subscription fees or unused AI usage, except where required by law. Cancelling stops future renewals but does not refund the current period. The one automatic exception is fairness-based: if a request fails on our side, that AI usage is refunded to your balance — you are never charged for a failed result. See our <a href="'.route('refund').'">Refund Policy</a> and <a href="'.route('terms').'">Terms of Service</a> for details.</p>
                        '],

                        ['id' => 'api-overview', 'h' => 'API overview', 'html' => '
                            <p>Beyond the dashboard, '.config('app.name').' offers a <strong>developer API</strong> to build match scoring and resume parsing into your own job board or ATS. You call it over HTTPS with a single API key, and it draws from the same AI usage as your dashboard. Full request and response details live in the <a href="'.route('docs').'">API docs</a>.</p>
                        '],
                        ['id' => 'api-key', 'h' => 'Generate an API key', 'html' => '
                            <p>From your dashboard, mint an API key. It is shown <strong>once</strong> — copy it immediately and store it like a password. You can regenerate it (which revokes the old one) or revoke it entirely at any time.</p>
                            <ul><li>Keep the key server-side; never put it in browser or mobile code.</li><li>Anyone with your key can use your AI usage.</li></ul>
                        '],
                        ['id' => 'authentication', 'h' => 'Authentication', 'html' => '
                            <p>Send your key as a Bearer token on every request — <code class="'.$ico.'">Authorization: Bearer YOUR_API_KEY</code> — along with <code class="'.$ico.'">Accept: application/json</code>. A missing or invalid key returns <code class="'.$ico.'">401 unauthenticated</code>. See the <a href="'.route('docs').'">API docs</a> for full examples.</p>
                        '],
                        ['id' => 'endpoints', 'h' => 'Endpoints', 'html' => '
                            <p>Two endpoints cover the workflow: <code class="'.$ico.'">/api/v1/matching-score</code> returns a match score with a written reason for a job and an application, and <code class="'.$ico.'">/api/v1/parse-resume</code> turns a CV file into a structured profile. Full fields, formats and examples are in the <a href="'.route('docs').'">API docs</a>.</p>
                        '],
                        ['id' => 'async', 'h' => 'Async jobs', 'html' => '
                            <p>For large volumes, use the <code class="'.$ico.'">/async</code> endpoints. They return a <code class="'.$ico.'">job_id</code> immediately; poll <code class="'.$ico.'">/api/v1/jobs/{id}</code> until the status is <code class="'.$ico.'">completed</code> (or <code class="'.$ico.'">failed</code>). Polling does not use AI usage.</p>
                        '],

                        ['id' => 'errors', 'h' => 'Common errors', 'html' => '
                            <p>API errors return a JSON body with an <code class="'.$ico.'">error.code</code> and message. The most common:</p>
                            <ul>
                                <li><code class="'.$ico.'">401 unauthenticated</code> — key missing, invalid, or revoked.</li>
                                <li><code class="'.$ico.'">402 no_active_subscription</code> / <code class="'.$ico.'">insufficient_ai_usage</code> — no active plan, or not enough AI usage left for the request.</li>
                                <li><code class="'.$ico.'">422 validation_failed</code> — the request body is missing or invalid (details in <code class="'.$ico.'">errors</code>).</li>
                                <li><code class="'.$ico.'">415 unsupported_file_type</code> — the uploaded file type is not supported.</li>
                                <li><code class="'.$ico.'">429 rate_limited</code> — too many requests; slow down or use the async endpoints.</li>
                                <li><code class="'.$ico.'">502 ai_provider_error</code> — the AI step failed; the request is refunded automatically.</li>
                            </ul>
                        '],
                        ['id' => 'failed-calls', 'h' => 'Failed requests & refunds', 'html' => '
                            <p>If a request is charged but the AI step fails on our side, we automatically <strong>refund that AI usage</strong> and mark it as refunded in your usage log. You only pay for results you actually receive.</p>
                        '],
                        ['id' => 'data-security', 'h' => 'Data & security', 'html' => '
                            <p>Traffic is encrypted over HTTPS, API keys are stored hashed, and uploaded files are stored privately and served only through your account. We do not use your submitted content to train models. See the <a href="'.route('privacy').'">Privacy Policy</a> for the full picture.</p>
                        '],
                        ['id' => 'support', 'h' => 'Still need help?', 'html' => '
                            <p>Can&rsquo;t find what you need? Our team is happy to help with your workflow, billing, integration, or anything else. <a href="'.route('contact').'">Contact us</a> and we&rsquo;ll reply within one business day.</p>
                        '],
                    ];
                @endphp

                @foreach ($sections as $s)
                    <section id="{{ $s['id'] }}" data-help-section
                             class="relative sm:pl-9 pb-12 scroll-mt-24 last:pb-0">
                        <span class="hidden sm:flex absolute left-0 top-1.5 h-3.5 w-3.5 items-center justify-center rounded-full bg-white ring-2 ring-indigo-500" aria-hidden="true">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                        </span>
                        <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ $s['h'] }}</h2>
                        <div class="mt-3 space-y-3 text-slate-600 leading-relaxed
                                    [&_p]:leading-relaxed
                                    [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5 [&_li]:marker:text-indigo-400
                                    [&_a]:text-indigo-600 [&_a]:font-medium [&_a]:underline hover:[&_a]:text-indigo-800
                                    [&_strong]:text-slate-900 [&_strong]:font-semibold">
                            {!! $s['html'] !!}
                        </div>
                    </section>
                @endforeach
            </div>
        </div>

        {{-- Sidebar (right) --}}
        <aside class="hidden lg:block order-2">
            <nav class="sticky top-24 space-y-7 pb-10" aria-label="Help topics">
                @foreach ($nav as $group => $items)
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</div>
                        <ul class="mt-3 space-y-1 border-l border-slate-200">
                            @foreach ($items as $item)
                                <li>
                                    <a href="#{{ $item['id'] }}" data-help-link="{{ $item['id'] }}"
                                       class="block -ml-px border-l-2 border-transparent pl-4 py-1 text-sm text-slate-600 hover:text-slate-900 hover:border-slate-300 transition">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>
    </div>

    {{-- Scroll-spy: highlight the active topic in the right nav --}}
    <script>
        (function () {
            var links = document.querySelectorAll('[data-help-link]');
            if (!links.length || !('IntersectionObserver' in window)) return;

            var map = {};
            links.forEach(function (link) { map[link.dataset.helpLink] = link; });

            function activate(id) {
                links.forEach(function (link) {
                    var on = link.dataset.helpLink === id;
                    link.classList.toggle('text-indigo-600', on);
                    link.classList.toggle('border-indigo-600', on);
                    link.classList.toggle('font-semibold', on);
                    link.classList.toggle('text-slate-600', !on);
                    link.classList.toggle('border-transparent', !on);
                });
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) activate(entry.target.id);
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            document.querySelectorAll('[data-help-section]').forEach(function (el) {
                observer.observe(el);
            });
        })();
    </script>

</x-app-layout>
