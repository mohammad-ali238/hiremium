<x-app-layout :title="'Pricing'">

    @if (session('error'))
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            <div class="rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        </div>
    @endif
    @if (session('status'))
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        </div>
    @endif

    <section class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">
                Plans that scale with your hiring.
            </h1>
            <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                Pay for AI usage, nothing else. Every plan includes bulk CV upload, automatic match scoring,
                and a report for each candidate — pick the AI usage that fits your volume.
            </p>
        </div>
    </section>

    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-site.pricing-grid
                :plans="$plans"
                :parsing-costs="$parsingCosts"
                :current-subscription="$currentSubscription ?? null"
                :billing-enabled="$billingEnabled ?? false" />
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-20 bg-slate-50 border-t border-slate-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 text-center">FAQ</h2>
            <div class="mt-10 space-y-6">
                @php
                    $faqs = [
                        ['q' => 'What is “AI usage”?', 'a' => 'Your plan includes a monthly amount of AI usage, measured in Requests. Analyzing one CV against a job uses one AI Request. When a request fails on our side, it is refunded automatically — you only spend AI usage on results you actually get.'],
                        ['q' => 'What counts as one AI Request?', 'a' => 'One Analyze — reading a candidate’s CV and scoring it against your job — uses one AI Request. Uploading files and browsing your ranked shortlists does not.'],
                        ['q' => 'Does AI usage roll over?',     'a' => 'No. When your subscription renews or is replaced, your AI usage resets to the plan’s monthly amount.'],
                        ['q' => 'Can I switch plans?',       'a' => 'While a subscription is active you can upgrade to a higher plan at any time — your subscription is switched over and prorated automatically. Same or lower plans become available again once your current period ends.'],
                        ['q' => 'How do I get started?', 'a' => 'Pick a plan and check out in a couple of minutes; your AI usage is available immediately. Need a tailored volume for a bigger team? Get in touch and we’ll help.'],
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

</x-app-layout>
