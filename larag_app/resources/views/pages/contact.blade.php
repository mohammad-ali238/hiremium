<x-app-layout :title="'Contact us'">

    <section class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">
                Talk to a human
            </h1>
            <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                Pre-sales question, custom plan, integration help — drop us a line and we'll get back within one business day.
            </p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-3 gap-12">

            {{-- Contact info --}}
            <div class="lg:col-span-1 space-y-6">
                @foreach ([
                    ['title' => 'Quick replies', 'body' => 'Send the form and our team gets back to you within one business day — usually much sooner.', 'icon' => 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
                    ['title' => 'Talk to real people', 'body' => 'No bots, no ticket mazes. You reach an engineer who actually knows the API and can help you integrate.', 'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'],
                    ['title' => 'Integration help', 'body' => 'Sizing a plan, wiring the API into your job board, or planning a bulk import? Tell us your use case and we will guide you.', 'icon' => 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z'],
                ] as $item)
                    <div class="flex gap-4">
                        <div class="h-10 w-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-900">{{ $item['title'] }}</div>
                            <div class="mt-1 text-sm text-slate-600 leading-relaxed">{{ $item['body'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Contact form (shared with the home page) --}}
            <div class="lg:col-span-2">
                <x-site.contact-form :topics="$topics" :recaptcha-enabled="! empty($recaptchaEnabled)" :recaptcha-site-key="$recaptchaSiteKey ?? ''" id="hiremium-contact-form" />
            </div>
        </div>
    </section>

</x-app-layout>
