@php
    $navLinks = [
        ['label' => 'How it works', 'route' => 'how-it-works'],
        ['label' => 'Free Tools',   'type'  => 'tools'],
        ['label' => 'Pricing',      'route' => 'pricing'],
        ['label' => 'About',        'route' => 'about'],
        ['label' => 'Contact us',   'route' => 'contact'],
    ];

    // Free tools are listed straight from the registry, so a new tool appears
    // in the menu as soon as it is registered and switched on.
    $freeTools = app(\App\Services\Tools\ToolRegistry::class)->enabled();
    $toolsActive = request()->routeIs('tools.*');
@endphp

<header
    x-data="{ open: false }"
    class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-200"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">

            {{-- Logo --}}
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <img src="{{ asset('logo.svg') }}" alt="Hiremium" class="h-10 w-auto" />
            </a>

            {{-- Desktop nav --}}
            <nav class="hidden md:flex md:items-center md:gap-8">

                @foreach ($navLinks as $link)
                    @if (($link['type'] ?? null) === 'tools')
                        @if (count($freeTools) > 0)
                    {{-- Free Tools: click to open, click outside or Escape to close --}}
                    <div x-data="{ tools: false }" class="relative">
                        <button type="button"
                                @click="tools = !tools" @click.outside="tools = false" @keydown.escape.window="tools = false"
                                :aria-expanded="tools ? 'true' : 'false'" aria-haspopup="true"
                                class="flex items-center gap-1 text-sm font-medium transition
                                       {{ $toolsActive ? 'text-indigo-600' : 'text-slate-600 hover:text-slate-900' }}">
                            Free Tools
                            <svg class="h-4 w-4 text-slate-400 transition" :class="tools && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 011.08 1.04l-4.24 4.38a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="tools" x-cloak x-transition.opacity
                             class="absolute left-0 mt-3 w-80 rounded-xl bg-white shadow-lg ring-1 ring-slate-200 p-1.5">
                            @foreach ($freeTools as $tool)
                                <a href="{{ route('tools.show', $tool->slug()) }}"
                                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 transition hover:bg-slate-50">
                                    <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-100">
                                        <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tool->iconPath() }}" />
                                        </svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-slate-800 group-hover:text-indigo-700">{{ $tool->menuLabel() }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $tool->tagline() }}</span>
                                    </span>
                                </a>
                            @endforeach
                            <a href="{{ route('tools.index') }}"
                               class="mt-2 flex items-center gap-1 rounded-lg border-t border-slate-100 px-3 pt-4 pb-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                See all free tools
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                        </div>
                    </div>
                        @endif
                        @continue
                    @endif
                    @php $active = request()->routeIs($link['match'] ?? $link['route']); @endphp
                    <a href="{{ route($link['route']) }}"
                       class="text-sm font-medium transition
                              {{ $active ? 'text-indigo-600' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Right side: auth actions --}}
            <div class="hidden md:flex md:items-center md:gap-3">
                @auth
                    @if (auth()->user()->is_admin)
                        <a href="{{ url('/admin') }}"
                           class="text-sm font-medium text-slate-700 hover:text-slate-900">
                            Admin
                        </a>
                    @endif
                    <div x-data="{ menu: false }" class="relative">
                        <button @click="menu = !menu" @click.outside="menu = false"
                                class="flex items-center gap-2 rounded-full bg-slate-100 hover:bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white text-xs font-semibold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span>{{ auth()->user()->name }}</span>
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 011.08 1.04l-4.24 4.38a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="menu" x-cloak x-transition.opacity
                             class="absolute right-0 mt-2 w-48 rounded-lg bg-white shadow-lg ring-1 ring-slate-200 py-1">
                            <a href="{{ route('app.dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Dashboard</a>
                            <a href="{{ route('app.billing') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Billing</a>
                            <a href="{{ route('app.settings') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Log out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}"
                       class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Log in
                    </a>
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition">
                        Get started
                    </a>
                @endauth
            </div>

            {{-- Mobile hamburger --}}
            <button @click="open = !open"
                    class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-slate-700 hover:bg-slate-100"
                    aria-label="Toggle menu">
                <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak x-transition class="md:hidden border-t border-slate-200 bg-white">
        <div class="px-4 py-3 space-y-1">
            @foreach ($navLinks as $link)
                @if (($link['type'] ?? null) === 'tools')
                    @if (count($freeTools) > 0)
                <div x-data="{ tools: {{ $toolsActive ? 'true' : 'false' }} }">
                    <button type="button" @click="tools = !tools"
                            class="flex w-full items-center justify-between px-3 py-2 rounded-md text-base font-medium
                                   {{ $toolsActive ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50' }}">
                        Free Tools
                        <svg class="h-4 w-4 text-slate-400 transition" :class="tools && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 011.08 1.04l-4.24 4.38a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="tools" x-cloak x-transition class="mt-1 space-y-1 pl-3">
                        @foreach ($freeTools as $tool)
                            <a href="{{ route('tools.show', $tool->slug()) }}"
                               class="block px-3 py-2 rounded-md text-sm font-medium text-slate-600 hover:bg-slate-50">
                                {{ $tool->menuLabel() }}
                            </a>
                        @endforeach
                    </div>
                </div>
                    @endif
                    @continue
                @endif
                @php $active = request()->routeIs($link['match'] ?? $link['route']); @endphp
                <a href="{{ route($link['route']) }}"
                   class="block px-3 py-2 rounded-md text-base font-medium
                          {{ $active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
        <div class="border-t border-slate-200 px-4 py-3 space-y-2">
            @auth
                <a href="{{ route('app.dashboard') }}" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Dashboard</a>
                @if (auth()->user()->is_admin)
                    <a href="{{ url('/admin') }}" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Admin</a>
                @endif
                <a href="{{ route('app.billing') }}" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Billing</a>
                <a href="{{ route('app.settings') }}" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Log in</a>
                <a href="{{ route('register') }}" class="block px-3 py-2 rounded-md text-base font-semibold text-white bg-indigo-600 hover:bg-indigo-700 text-center">Get started</a>
            @endauth
        </div>
    </div>
</header>
