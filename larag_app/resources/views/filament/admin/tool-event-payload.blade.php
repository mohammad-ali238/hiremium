{{-- The raw exchange behind one AI call, kept only while "store raw payloads"
     is on. This is where a bad score or a truncated answer gets diagnosed. --}}
<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
        @foreach ([
            'Run' => '#'.$event->aitools_run_id,
            'Stage' => $event->stage,
            'Attempt' => $event->attempt,
            'Status' => $event->status,
            'Provider' => $event->provider ?? '—',
            'Model' => $event->model ?? '—',
            'Source' => $event->provider_inherited ? 'AI Settings' : 'per-tool override',
            'Tokens' => $event->tokens_total !== null ? number_format($event->tokens_total) : '—',
            'Time' => $event->duration_ms !== null ? number_format($event->duration_ms).' ms' : '—',
            'Logged' => $event->created_at?->format('Y-m-d H:i:s'),
        ] as $label => $value)
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="mt-0.5 break-words text-gray-950 dark:text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($event->error_message)
        <div class="rounded-lg bg-danger-50 p-3 dark:bg-danger-500/10">
            <p class="font-medium text-danger-700 dark:text-danger-400">{{ $event->error_code }}</p>
            <p class="mt-1 whitespace-pre-wrap break-words text-danger-700 dark:text-danger-400">{{ $event->error_message }}</p>
        </div>
    @endif

    @if ($event->payload)
        <div>
            <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Payload</h3>
            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">
            No payload was stored for this call — either it made no AI request, or
            "store raw AI payloads" was off in Free Tools settings when it ran.
        </p>
    @endif
</div>
