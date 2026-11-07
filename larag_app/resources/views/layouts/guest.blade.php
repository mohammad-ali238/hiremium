<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Hiremium') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <style>
            [x-cloak] { display: none !important; }
            html, body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-slate-100 text-slate-900">
        <div class="min-h-screen flex flex-col">

            {{-- Top bar --}}
            <header class="w-full">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                    <a href="{{ url('/') }}" class="flex items-center">
                        <img src="{{ asset('logo.svg') }}" alt="Hiremium" class="h-10 w-auto" />
                    </a>
                    <a href="{{ url('/') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800 transition">
                        &larr; {{ __('Back to home') }}
                    </a>
                </div>
            </header>

            {{-- Centered auth card --}}
            <main class="flex-1 flex items-center justify-center px-4 sm:px-6 py-8">
                <div class="w-full max-w-5xl bg-white rounded-2xl shadow-xl ring-1 ring-slate-200/70 overflow-hidden lg:grid lg:grid-cols-5">

                    {{-- Brand panel --}}
                    <aside class="hidden lg:flex lg:col-span-2 relative overflow-hidden flex-col bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 text-white p-10">
                        <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
                            <div class="absolute -top-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                            <div class="absolute -bottom-24 -right-16 h-72 w-72 rounded-full bg-violet-400/20 blur-3xl"></div>
                        </div>

                        <div class="relative flex flex-col h-full">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-200">Smarter Hiring. Powered by AI.</p>
                                <h1 class="mt-3 text-2xl xl:text-3xl font-extrabold tracking-tight leading-tight">
                                    Score every application.<br />Interview only the best.
                                </h1>
                                <p class="mt-3 text-sm text-indigo-100 leading-relaxed">
                                    One simple API turns each resume into a ranked, explainable
                                    match score for your job board.
                                </p>
                            </div>

                            <img src="{{ asset('images/auth-illustration.svg') }}"
                                 alt="Resume being scored by AI"
                                 class="w-full max-w-xs mx-auto my-8 drop-shadow-xl" />

                            <ul class="mt-auto space-y-2.5 text-sm text-indigo-50">
                                <li class="flex items-center gap-2.5">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-300 text-xs">&#10003;</span>
                                    AI match scores with clear reasons
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-300 text-xs">&#10003;</span>
                                    Resume parsing from PDF, Word &amp; images
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-300 text-xs">&#10003;</span>
                                    AI-usage pricing — failed requests refunded
                                </li>
                            </ul>
                        </div>
                    </aside>

                    {{-- Form panel --}}
                    <div class="lg:col-span-3 p-6 sm:p-10 lg:p-12">
                        @isset($title)
                            <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h2>
                        @endisset
                        @isset($subtitle)
                            <p class="mt-2 text-sm text-slate-500 leading-relaxed">{{ $subtitle }}</p>
                        @endisset

                        <div @class(['mt-7' => isset($title) || isset($subtitle)])>
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </main>

            <footer class="pb-6 text-center text-xs text-slate-400">
                &copy; {{ date('Y') }} Hiremium &middot; {{ __('AI match scoring & resume parsing') }}
            </footer>
        </div>
    </body>
</html>
