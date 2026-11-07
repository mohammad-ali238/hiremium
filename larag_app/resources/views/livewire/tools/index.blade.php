<div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Free tools</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-slate-900">Try Hiremium's AI, free</h1>
        <p class="mt-4 text-lg text-slate-600">
            No account needed — confirm your email and the result is yours to keep and share.
        </p>
    </div>

    @if (count($tools) === 0)
        <div class="mt-12 rounded-2xl border border-slate-200 bg-white p-10 text-center">
            <p class="text-slate-600">Our free tools are taking a short break. Please check back soon.</p>
        </div>
    @else
        <div class="mt-12 grid gap-6 sm:grid-cols-2">
            @foreach ($tools as $tool)
                <a href="{{ route('tools.show', $tool->slug()) }}"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-indigo-300 hover:shadow-lg">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tool->iconPath() }}" />
                        </svg>
                    </span>
                    <h2 class="mt-4 text-lg font-bold text-slate-900 group-hover:text-indigo-600">{{ $tool->name() }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $tool->tagline() }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
