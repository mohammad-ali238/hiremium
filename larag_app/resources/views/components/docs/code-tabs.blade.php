@props(['samples' => [], 'label' => null])

@php
    $langNames = ['curl' => 'cURL', 'php' => 'PHP', 'node' => 'Node.js', 'python' => 'Python'];
@endphp

<div x-data class="rounded-xl bg-slate-900 ring-1 ring-slate-800 shadow-lg overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-x-3 px-4 border-b border-slate-800">
        @if ($label)
            <span class="py-3 text-xs text-slate-400 font-mono">{{ $label }}</span>
        @endif
        <div class="flex gap-1">
            @foreach ($samples as $lang => $code)
                <button type="button"
                        @click="$store.docsLang.set('{{ $lang }}')"
                        :class="$store.docsLang.current === '{{ $lang }}'
                            ? 'text-white border-indigo-400'
                            : 'text-slate-400 border-transparent hover:text-slate-200'"
                        class="px-3 py-3 text-xs font-semibold border-b-2 -mb-px transition">
                    {{ $langNames[$lang] ?? $lang }}
                </button>
            @endforeach
        </div>
    </div>
    @foreach ($samples as $lang => $code)
        <pre x-show="$store.docsLang.current === '{{ $lang }}'" @if (! $loop->first) x-cloak @endif
             class="p-5 text-xs sm:text-sm text-slate-200 font-mono overflow-x-auto leading-relaxed">{{ $code }}</pre>
    @endforeach
</div>
