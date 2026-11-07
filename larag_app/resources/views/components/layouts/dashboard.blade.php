{{--
    Hiremium dashboard shell (Phase 0)
    Livewire full-page layout: <html> + sidebar + topbar + {{ $slot }}.
    Consumes the design tokens in resources/css/app.css; dark mode toggles the
    `.dark` class on <html>. Only the Dashboard link is wired yet; the rest are
    placeholders so the workspace reads as a complete platform.
--}}
@php
    $icon = function (string $name): string {
        $p = [
            'home'     => '<path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
            'briefcase'=> '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
            'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'star'     => '<path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 18l-5.8 3 1.1-6.5L2.6 9.8l6.5-.9z"/>',
            'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 9 5-5 5 5"/><path d="M12 4v12"/>',
            'sparkles' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><path d="M6.3 6.3 9 9M15 15l2.7 2.7M17.7 6.3 15 9M9 15l-2.7 2.7"/>',
            'chart'    => '<path d="M3 3v18h18"/><path d="M7 15l3-4 3 3 4-6"/>',
            'scale'    => '<path d="M12 3v18"/><path d="M6 8h12"/><path d="m6 8-3 6h6zM18 8l-3 6h6z"/>',
            'report'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/>',
            'activity' => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
            'team'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'credit'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            'cog'      => '<path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
            'card'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 9h20M6 15h4"/>',
            'help'     => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4"/><path d="M12 17h.01"/>',
            'key'      => '<circle cx="7.5" cy="15.5" r="4"/><path d="M10.3 12.7 20 3"/><path d="m17 6 2 2"/><path d="m14 9 2 2"/>',
        ];
        return $p[$name] ?? '';
    };

    $groups = [
        'Overview' => [
            ['Dashboard', 'home', route('app.dashboard'), request()->routeIs('app.dashboard')],
        ],
        'Recruitment' => [
            ['Jobs', 'briefcase', route('app.jobs'), request()->routeIs('app.jobs*')],
            ['Matched Candidates', 'users', route('app.candidates'), request()->routeIs('app.candidates*')],
            ['Shortlists', 'star', route('app.shortlists'), request()->routeIs('app.shortlists')],
            ['Resume Upload', 'upload', route('app.upload'), request()->routeIs('app.upload') || request()->routeIs('app.batches*')],
        ],
        'Reports' => [
            ['Screening Reports', 'report', route('app.reports'), request()->routeIs('app.reports*')],
            ['Screening Insights', 'chart', route('app.insights'), request()->routeIs('app.insights')],
            ['Candidate Comparison', 'scale', route('app.compare'), request()->routeIs('app.compare')],
        ],
        'Workspace' => [
            ['Team Members', 'team', route('app.team'), request()->routeIs('app.team')],
            ['AI Usage', 'credit', route('app.usage'), request()->routeIs('app.usage')],
            ['Activity Log', 'activity', route('app.activity'), request()->routeIs('app.activity')],
        ],
        'Account' => array_merge(
            [
                ['Settings', 'cog', route('app.settings'), request()->routeIs('app.settings')],
                ['Billing', 'card', route('app.billing'), request()->routeIs('app.billing')],
            ],
            auth()->user()?->isOwnerOfCurrentWorkspace() ? [
                ['API', 'key', route('app.api'), request()->routeIs('app.api')],
            ] : [],
            [
                ['Help Center', 'help', route('help'), false],
            ],
        ),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · Hiremium</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- No-flash theme: apply saved/system preference before paint. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('hm-theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <style>[x-cloak]{display:none !important;}</style>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/dashboard.js'])
</head>
<body class="h-full bg-bg text-app-text antialiased">
<div x-data="{ sidebar: false }" class="min-h-full">

    {{-- Mobile overlay --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false"
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-surface border-r border-line
               transform transition-transform duration-200 lg:translate-x-0"
        :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
        <div class="h-16 flex items-center gap-2 px-5 border-b border-line">
            {{-- Logo links to the public home page. Wordmark is inside the SVG; the dark variant has a white "Hire". --}}
            <a href="{{ route('home') }}" class="inline-flex items-center rounded-md focus:outline-none focus:ring-2 focus:ring-primary/40" aria-label="Hiremium — go to home page" title="Home">
                <img src="{{ asset('logo.svg') }}" alt="Hiremium" class="h-7 w-auto dark:hidden">
                <img src="{{ asset('logo-dark.svg') }}" alt="Hiremium" class="hidden h-7 w-auto dark:block">
            </a>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            @foreach ($groups as $label => $items)
                <div>
                    <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $label }}</p>
                    <ul class="space-y-1">
                        @foreach ($items as [$name, $ic, $href, $active])
                            <li>
                                <a href="{{ $href }}"
                                   @class([
                                       'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                       'bg-primary-soft text-primary' => $active,
                                       'text-muted hover:bg-primary-soft/60 hover:text-app-text' => ! $active,
                                   ])>
                                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                         stroke-linejoin="round">{!! $icon($ic) !!}</svg>
                                    <span>{{ $name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- Main column --}}
    <div class="lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 h-16 flex items-center gap-3 px-4 sm:px-6
                       bg-surface/80 backdrop-blur border-b border-line">
            <button @click="sidebar = true" class="lg:hidden -ml-1 p-2 text-muted hover:text-app-text" aria-label="Open menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            {{-- Global search — submits to the candidate list --}}
            <form action="{{ route('app.candidates') }}" method="GET" class="hidden sm:flex items-center flex-1 max-w-md">
                <div class="relative w-full">
                    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="search" placeholder="Search candidates…"
                           class="w-full rounded-lg border-line bg-bg pl-9 pr-3 py-2 text-sm text-app-text
                                  placeholder:text-muted focus:border-primary focus:ring-brand">
                </div>
            </form>

            <div class="ml-auto flex items-center gap-1">
                {{-- Theme toggle --}}
                <button
                    x-data
                    @click="
                        const el = document.documentElement;
                        const dark = el.classList.toggle('dark');
                        try { localStorage.setItem('hm-theme', dark ? 'dark' : 'light'); } catch (e) {}
                    "
                    class="p-2 rounded-lg text-muted hover:text-app-text hover:bg-primary-soft/60" aria-label="Toggle theme">
                    <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>
                    <svg class="h-5 w-5 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                </button>

                {{-- Notifications bell --}}
                <livewire:notification-bell />

                {{-- User menu --}}
                <div x-data="{ open: false }" class="relative ml-1">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-lg p-1 pr-2 hover:bg-primary-soft/60">
                        @if (auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" class="h-8 w-8 rounded-full object-cover" alt="">
                        @else
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-primary text-primary-fg text-sm font-semibold">
                                {{ strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}
                            </span>
                        @endif
                        <span class="hidden sm:block text-sm font-medium text-app-text max-w-[10rem] truncate">{{ auth()->user()->name ?? '' }}</span>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition
                         class="absolute right-0 mt-2 w-48 rounded-lg border border-line bg-card shadow-lg py-1">
                        <a href="{{ route('app.settings') }}" class="block px-4 py-2 text-sm text-muted hover:bg-primary-soft/60 hover:text-app-text">Settings</a>
                        <a href="{{ route('app.dashboard') }}" class="block px-4 py-2 text-sm text-muted hover:bg-primary-soft/60 hover:text-app-text">Billing</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-danger hover:bg-primary-soft/60">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    {{-- Command palette (Ctrl / ⌘ + K) --}}
    @php
        $commands = [
            ['Dashboard', route('app.dashboard')],
            ['Jobs', route('app.jobs')],
            ['New job', route('app.jobs.create')],
            ['Matched Candidates', route('app.candidates')],
            ['Shortlists', route('app.shortlists')],
            ['Resume Upload', route('app.upload')],
            ['Screening Insights', route('app.insights')],
            ['Candidate Comparison', route('app.compare')],
            ['Screening Reports', route('app.reports')],
            ['Team Members', route('app.team')],
            ['AI Usage', route('app.usage')],
            ['Activity Log', route('app.activity')],
            ['Settings', route('app.settings')],
        ];
    @endphp
    <div x-data="{ open: false, q: '', items: @js($commands),
             get filtered() { return this.q === '' ? this.items : this.items.filter(i => i[0].toLowerCase().includes(this.q.toLowerCase())); } }"
         x-on:keydown.window.meta.k.prevent="open = true; q = ''"
         x-on:keydown.window.ctrl.k.prevent="open = true; q = ''"
         x-on:keydown.escape.window="open = false"
         x-show="open" x-cloak class="fixed inset-0 z-[80]" style="display:none">
        <div class="fixed inset-0 bg-slate-900/50" @click="open = false"></div>
        <div class="relative mx-auto mt-24 w-full max-w-lg px-4">
            <div class="overflow-hidden rounded-xl border border-line bg-card shadow-xl" @click.outside="open = false">
                <input x-model="q" x-ref="cmd" x-effect="if (open) $nextTick(() => $refs.cmd.focus())"
                       placeholder="Jump to…"
                       class="w-full border-0 border-b border-line bg-transparent px-4 py-3 text-sm text-app-text placeholder:text-muted focus:ring-0">
                <ul class="max-h-72 overflow-y-auto py-1">
                    <template x-for="(item, i) in filtered" :key="i">
                        <li><a :href="item[1]" class="block px-4 py-2 text-sm text-app-text hover:bg-primary-soft/60" x-text="item[0]"></a></li>
                    </template>
                    <template x-if="filtered.length === 0"><li class="px-4 py-3 text-sm text-muted">No matches.</li></template>
                </ul>
                <div class="border-t border-line px-4 py-2 text-[11px] text-muted">Press <kbd class="rounded bg-line px-1">Esc</kbd> to close</div>
            </div>
        </div>
    </div>

    {{-- Global feedback components (single mount) --}}
    <x-ui.toast />
    <x-ui.confirm />
</div>
<x-impersonation-bar />
@livewireScriptConfig
</body>
</html>
