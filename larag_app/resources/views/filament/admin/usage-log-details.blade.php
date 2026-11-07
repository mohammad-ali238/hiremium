<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div><span class="font-semibold">User:</span> {{ $log->user?->email }}</div>
        <div><span class="font-semibold">Endpoint:</span> {{ $log->endpoint }}</div>
        <div><span class="font-semibold">Provider:</span> {{ $log->ai_provider ?? '—' }}</div>
        <div><span class="font-semibold">Model:</span> {{ $log->ai_model ?? '—' }}</div>
        <div><span class="font-semibold">Score:</span> {{ $log->score ?? '—' }}</div>
        <div><span class="font-semibold">Tokens:</span> {{ $log->tokens_used ?? '—' }}</div>
        <div><span class="font-semibold">IP:</span> {{ $log->ip_address ?? '—' }}</div>
        <div><span class="font-semibold">Status:</span> {{ $log->status }}</div>
    </div>

    @if ($log->error_message)
        <div>
            <div class="font-semibold mb-1">Error</div>
            <pre class="bg-red-50 p-2 rounded text-xs whitespace-pre-wrap">{{ $log->error_message }}</pre>
        </div>
    @endif

    <div>
        <div class="font-semibold mb-1">Request payload</div>
        <pre class="bg-gray-100 dark:bg-gray-800 p-2 rounded text-xs whitespace-pre-wrap overflow-auto max-h-64">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>

    @if ($log->response_payload)
        <div>
            <div class="font-semibold mb-1">Response payload</div>
            <pre class="bg-gray-100 dark:bg-gray-800 p-2 rounded text-xs whitespace-pre-wrap overflow-auto max-h-64">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
</div>
