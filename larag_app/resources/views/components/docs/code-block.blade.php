@props(['label' => null, 'code' => ''])

<div class="rounded-xl bg-slate-900 ring-1 ring-slate-800 shadow-lg overflow-hidden">
    @if ($label)
        <div class="px-4 py-3 border-b border-slate-800">
            <span class="text-xs text-slate-400 font-mono">{{ $label }}</span>
        </div>
    @endif
    <pre class="p-5 text-xs sm:text-sm text-slate-200 font-mono overflow-x-auto leading-relaxed">{{ $code }}</pre>
</div>
