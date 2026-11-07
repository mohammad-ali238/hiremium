@props(['variant' => 'hero', 'tone' => 'light'])
@php
    // "dark" tone is for the illustration sitting on a coloured/dark hero:
    // labels turn white and the connector arrows lighten so they stay readable.
    $labelFill = $tone === 'dark' ? '#ffffff' : '#475569';
    $arrowFill = $tone === 'dark' ? '#c7d2fe' : '#cbd5e1';
@endphp

@if ($variant === 'pipeline')
    {{-- Horizontal pipeline: bulk upload → analyze → score → rank --}}
    <svg {{ $attributes->merge(['class' => '']) }} viewBox="0 0 760 180" fill="none" xmlns="http://www.w3.org/2000/svg" role="img"
         aria-label="Workflow: upload CVs in bulk, AI analyzes each one, assigns a match score, then ranks the top scorers first.">
        <defs>
            <linearGradient id="wf-bar" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#6366f1"/>
                <stop offset="1" stop-color="#10b981"/>
            </linearGradient>
        </defs>

        {{-- arrows between nodes --}}
        @foreach ([160, 350, 540] as $ax)
            <line x1="{{ $ax }}" y1="80" x2="{{ $ax + 44 }}" y2="80" stroke="{{ $arrowFill }}" stroke-width="3" stroke-linecap="round"/>
            <path d="M{{ $ax + 44 }} 80 l-9 -6 v12 z" fill="{{ $arrowFill }}"/>
        @endforeach

        {{-- Node 1: bulk upload --}}
        <rect x="20" y="35" width="140" height="90" rx="14" fill="#eef2ff" stroke="#c7d2fe" stroke-width="1.5"/>
        <rect x="64" y="52" width="46" height="56" rx="6" fill="#ffffff" stroke="#a5b4fc" stroke-width="1.5"/>
        <rect x="56" y="60" width="46" height="56" rx="6" fill="#ffffff" stroke="#818cf8" stroke-width="1.5"/>
        <rect x="48" y="68" width="46" height="56" rx="6" fill="#ffffff" stroke="#6366f1" stroke-width="1.5"/>
        <line x1="56" y1="82" x2="86" y2="82" stroke="#c7d2fe" stroke-width="3" stroke-linecap="round"/>
        <line x1="56" y1="92" x2="86" y2="92" stroke="#c7d2fe" stroke-width="3" stroke-linecap="round"/>
        <line x1="56" y1="102" x2="76" y2="102" stroke="#c7d2fe" stroke-width="3" stroke-linecap="round"/>
        <path d="M90 44 v-14 m0 0 l-6 6 m6 -6 l6 6" stroke="#4f46e5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

        {{-- Node 2: analyze --}}
        <rect x="210" y="35" width="140" height="90" rx="14" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5"/>
        <rect x="252" y="55" width="46" height="56" rx="6" fill="#eef2ff" stroke="#c7d2fe" stroke-width="1.5"/>
        <circle cx="288" cy="86" r="16" fill="#ffffff" stroke="#4f46e5" stroke-width="3"/>
        <line x1="299" y1="97" x2="312" y2="110" stroke="#4f46e5" stroke-width="3.5" stroke-linecap="round"/>
        <path d="M282 86 l4 4 l8 -9" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

        {{-- Node 3: score --}}
        <rect x="400" y="35" width="140" height="90" rx="14" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5"/>
        <path d="M470 108 a28 28 0 1 1 0.1 0" fill="none" stroke="#eef2f7" stroke-width="8" stroke-linecap="round"/>
        <path d="M470 108 a28 28 0 1 1 24 -14" fill="none" stroke="url(#wf-bar)" stroke-width="8" stroke-linecap="round"/>
        <text x="470" y="86" text-anchor="middle" font-family="ui-sans-serif,system-ui,sans-serif" font-size="20" font-weight="800" fill="#4f46e5">91</text>

        {{-- Node 4: rank --}}
        <rect x="590" y="35" width="150" height="90" rx="14" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5"/>
        <rect x="612" y="52" width="106" height="12" rx="6" fill="url(#wf-bar)"/>
        <rect x="612" y="72" width="86" height="12" rx="6" fill="#c7d2fe"/>
        <rect x="612" y="92" width="64" height="12" rx="6" fill="#e2e8f0"/>

        {{-- labels --}}
        <g font-family="ui-sans-serif,system-ui,sans-serif" font-size="13" font-weight="600" fill="{{ $labelFill }}" text-anchor="middle">
            <text x="90" y="150">Bulk upload</text>
            <text x="280" y="150">AI reads each CV</text>
            <text x="470" y="150">Match score</text>
            <text x="665" y="150">Top scorers first</text>
        </g>
    </svg>
@else
    {{-- Hero: product panel showing a ranked candidate shortlist --}}
    <svg {{ $attributes->merge(['class' => '']) }} viewBox="0 0 520 432" fill="none" xmlns="http://www.w3.org/2000/svg" role="img"
         aria-label="A batch of uploaded CVs analyzed and ranked by match score, strongest match at the top.">
        <defs>
            <linearGradient id="hero-bar" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#6366f1"/>
                <stop offset="1" stop-color="#10b981"/>
            </linearGradient>
        </defs>

        {{-- panel --}}
        <rect x="10" y="10" width="500" height="412" rx="22" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5"/>

        {{-- header --}}
        <circle cx="44" cy="44" r="5" fill="#f87171"/>
        <circle cx="62" cy="44" r="5" fill="#fbbf24"/>
        <circle cx="80" cy="44" r="5" fill="#34d399"/>
        <text x="104" y="49" font-family="ui-sans-serif,system-ui,sans-serif" font-size="13" font-weight="600" fill="#94a3b8">Ranked candidates</text>
        <line x1="24" y1="68" x2="496" y2="68" stroke="#eef2f7" stroke-width="1.5"/>

        {{-- upload band: doc stack → analyze chip --}}
        <rect x="64" y="86" width="44" height="56" rx="6" fill="#ffffff" stroke="#a5b4fc" stroke-width="1.5"/>
        <rect x="54" y="94" width="44" height="56" rx="6" fill="#ffffff" stroke="#818cf8" stroke-width="1.5"/>
        <rect x="44" y="102" width="44" height="56" rx="6" fill="#eef2ff" stroke="#6366f1" stroke-width="1.5"/>
        <line x1="52" y1="116" x2="80" y2="116" stroke="#c7d2fe" stroke-width="3" stroke-linecap="round"/>
        <line x1="52" y1="126" x2="80" y2="126" stroke="#c7d2fe" stroke-width="3" stroke-linecap="round"/>
        <line x1="52" y1="136" x2="72" y2="136" stroke="#c7d2fe" stroke-width="3" stroke-linecap="round"/>

        <line x1="120" y1="130" x2="176" y2="130" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
        <path d="M176 130 l-9 -6 v12 z" fill="#cbd5e1"/>

        <rect x="188" y="106" width="112" height="48" rx="12" fill="#4f46e5"/>
        <path d="M212 124 l3 7 l7 3 l-7 3 l-3 7 l-3 -7 l-7 -3 l7 -3 z" fill="#ffffff"/>
        <text x="232" y="135" font-family="ui-sans-serif,system-ui,sans-serif" font-size="14" font-weight="700" fill="#ffffff">Analyze</text>

        <path d="M244 154 v18 m0 0 l-6 -6 m6 6 l6 -6" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

        {{-- ranked rows --}}
        {{-- top match highlight --}}
        <rect x="28" y="182" width="464" height="44" rx="12" fill="#f5f3ff"/>
        <rect x="28" y="182" width="4" height="44" rx="2" fill="#4f46e5"/>

        @php
            $rows = [
                ['cy' => 204, 'avatar' => '#6366f1', 'w' => 140, 'score' => 91],
                ['cy' => 256, 'avatar' => '#0ea5e9', 'w' => 132, 'score' => 87],
                ['cy' => 308, 'avatar' => '#f59e0b', 'w' => 110, 'score' => 74],
                ['cy' => 360, 'avatar' => '#64748b', 'w' => 96,  'score' => 66],
            ];
        @endphp
        @foreach ($rows as $r)
            @php $cy = $r['cy']; @endphp
            <circle cx="54" cy="{{ $cy }}" r="15" fill="{{ $r['avatar'] }}" opacity="0.9"/>
            <rect x="78" y="{{ $cy - 12 }}" width="104" height="9" rx="4" fill="#e2e8f0"/>
            <rect x="78" y="{{ $cy + 3 }}" width="66" height="7" rx="3" fill="#eef2f7"/>
            <rect x="300" y="{{ $cy - 6 }}" width="150" height="12" rx="6" fill="#eef2f7"/>
            <rect x="300" y="{{ $cy - 6 }}" width="{{ $r['w'] }}" height="12" rx="6" fill="url(#hero-bar)"/>
            <text x="464" y="{{ $cy + 5 }}" font-family="ui-sans-serif,system-ui,sans-serif" font-size="15" font-weight="800" fill="#0f172a">{{ $r['score'] }}</text>
        @endforeach
    </svg>
@endif
