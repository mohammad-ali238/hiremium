{{--
    Layout for public TOOL pages (/tools, /tools/{slug}).

    Same chrome as the rest of the marketing site — header, footer, SEO — but it
    loads the Free Tools JS bundle instead of app.js, because these pages host a
    Livewire component and Livewire must own the only Alpine on the page
    (see resources/js/tools.js).
--}}
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

        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/tools.js'])
    </head>
    <body class="min-h-full antialiased bg-slate-50 text-slate-900 flex flex-col">
        <x-site.header />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <x-site.footer />

        @livewireScriptConfig
    </body>
</html>
