@props(['title', 'updated' => 'July 2026'])

<x-app-layout :title="$title">
    <section class="bg-white border-b border-slate-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">{{ $title }}</h1>
            <p class="mt-4 text-sm text-slate-500">Last updated: {{ $updated }}</p>
        </div>
    </section>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14
                [&_h2]:text-xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-slate-900 [&_h2]:mt-12 [&_h2:first-child]:mt-0 [&_h2]:scroll-mt-24
                [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-slate-900 [&_h3]:mt-6
                [&_p]:mt-3 [&_p]:text-slate-600 [&_p]:leading-relaxed
                [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2 [&_ul]:text-slate-600 [&_li]:leading-relaxed [&_li]:marker:text-indigo-400
                [&_a]:text-indigo-600 [&_a]:font-medium [&_a]:underline hover:[&_a]:text-indigo-800
                [&_strong]:text-slate-900 [&_strong]:font-semibold">
        {{ $slot }}

        <div class="mt-14 rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-6 sm:p-8 text-center">
            <p class="!mt-0 text-slate-700">Have a question about this {{ $title }}?</p>
            <a href="{{ route('contact') }}"
               class="mt-4 inline-flex items-center justify-center rounded-md bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-semibold !text-white !no-underline shadow-sm transition">
                Contact us
            </a>
        </div>
    </div>
</x-app-layout>
