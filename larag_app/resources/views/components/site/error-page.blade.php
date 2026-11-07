@props(['code' => 404, 'title' => 'Page not found', 'message' => null])
{{-- Shared, on-brand error page: same gradient hero language as the marketing site, an inline SVG
     illustration, and useful exits. Used by resources/views/errors/*.blade.php. --}}
<x-app-layout :title="$title">
    <section class="relative overflow-hidden bg-gradient-to-br from-violet-700 via-indigo-700 to-indigo-800 text-white">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-28 -left-20 h-96 w-96 rounded-full bg-violet-400/20 blur-3xl"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-indigo-100 text-xs font-semibold ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                    Error {{ $code }}
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight">{{ $title }}</h1>
                <p class="mt-5 text-lg text-indigo-100 leading-relaxed max-w-xl">
                    {{ $message ?? 'The page you were looking for does not exist, has moved, or the link you followed is out of date.' }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ url('/') }}"
                       class="inline-flex items-center justify-center rounded-md bg-white text-slate-900 hover:bg-slate-100 px-6 py-3 text-base font-semibold shadow-sm transition">
                        Back to home
                    </a>
                    @auth
                        <a href="{{ url('/app') }}"
                           class="inline-flex items-center justify-center rounded-md bg-indigo-700/40 hover:bg-indigo-700/60 ring-1 ring-white/20 px-6 py-3 text-base font-semibold text-white transition">
                            Open dashboard
                        </a>
                    @else
                        <a href="{{ route('help') }}"
                           class="inline-flex items-center justify-center rounded-md bg-indigo-700/40 hover:bg-indigo-700/60 ring-1 ring-white/20 px-6 py-3 text-base font-semibold text-white transition">
                            Help center
                        </a>
                    @endauth
                    <a href="{{ route('contact') }}"
                       class="inline-flex items-center justify-center rounded-md px-6 py-3 text-base font-semibold text-indigo-100 hover:text-white transition">
                        Contact us &rarr;
                    </a>
                </div>
            </div>

            <div class="rounded-2xl bg-white/5 ring-1 ring-white/15 p-8 backdrop-blur-sm">
                {{-- Illustration: a document stack with a searching magnifier that found nothing --}}
                <svg class="w-full max-w-md mx-auto" viewBox="0 0 420 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Illustration: a search that found nothing">
                    <defs>
                        <linearGradient id="err-bar" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="#a5b4fc"/>
                            <stop offset="1" stop-color="#6ee7b7"/>
                        </linearGradient>
                    </defs>
                    {{-- document stack --}}
                    <rect x="120" y="70" width="150" height="190" rx="14" fill="#ffffff" fill-opacity="0.12" stroke="#c7d2fe" stroke-opacity="0.6" stroke-width="1.5"/>
                    <rect x="105" y="85" width="150" height="190" rx="14" fill="#ffffff" fill-opacity="0.16" stroke="#c7d2fe" stroke-opacity="0.8" stroke-width="1.5"/>
                    <rect x="90" y="100" width="150" height="190" rx="14" fill="#ffffff" stroke="#e0e7ff" stroke-width="1.5"/>
                    <rect x="112" y="128" width="72" height="10" rx="5" fill="url(#err-bar)"/>
                    <rect x="112" y="150" width="104" height="8" rx="4" fill="#c7d2fe"/>
                    <rect x="112" y="168" width="92" height="8" rx="4" fill="#e2e8f0"/>
                    <rect x="112" y="186" width="98" height="8" rx="4" fill="#e2e8f0"/>
                    <rect x="112" y="204" width="60" height="8" rx="4" fill="#e2e8f0"/>
                    {{-- big code --}}
                    <text x="330" y="150" text-anchor="middle" font-family="ui-sans-serif,system-ui,sans-serif" font-size="64" font-weight="800" fill="#ffffff" fill-opacity="0.95">{{ $code }}</text>
                    {{-- magnifier --}}
                    <circle cx="262" cy="212" r="38" fill="#4f46e5" fill-opacity="0.35" stroke="#ffffff" stroke-width="6"/>
                    <line x1="290" y1="240" x2="322" y2="272" stroke="#ffffff" stroke-width="10" stroke-linecap="round"/>
                    <path d="M248 198 l28 28 M276 198 l-28 28" stroke="#fda4af" stroke-width="6" stroke-linecap="round"/>
                    {{-- sparkles --}}
                    <path d="M60 60 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 l8 -3 z" fill="#c7d2fe" fill-opacity="0.9"/>
                    <path d="M370 240 l2 6 l6 2 l-6 2 l-2 6 l-2 -6 l-6 -2 l6 -2 z" fill="#6ee7b7" fill-opacity="0.9"/>
                </svg>
            </div>
        </div>
    </section>
</x-app-layout>
