<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-site.seo :title="$title ?? null" :seo="$seo ?? null" />

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <style>
            [x-cloak] { display: none !important; }
            html, body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Per-page <head> additions: an article's own CSS, feed autodiscovery. --}}
        @stack('head')
    </head>
    <body class="min-h-full antialiased bg-slate-50 text-slate-900 flex flex-col">
        <x-site.header />

        @isset($header)
            <div class="bg-white border-b border-slate-200">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </div>
        @endisset

        <main class="flex-1">
            {{ $slot }}
        </main>

        <x-site.footer />

        <x-impersonation-bar />
    </body>
</html>
