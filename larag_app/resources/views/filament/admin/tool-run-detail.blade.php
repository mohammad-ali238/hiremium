{{-- One free-tool run, whole: who asked, what they asked, what came back, and
     what each stage cost. Everything the admin needs without leaving the list. --}}
@php
    $report = (array) $run->output;
    $job = (array) $run->input_data;
    $candidate = (array) ($report['candidate'] ?? []);
    $file = $run->primaryFile();
    $events = $run->events()->orderBy('id')->get();
@endphp

<div class="space-y-6 text-sm">

    {{-- Identity and outcome --}}
    <div>
        <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Run</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
            @foreach ([
                'Tool' => app(\App\Services\Tools\ToolRegistry::class)->find($run->tool)?->name() ?? $run->tool,
                'Status' => ucfirst(str_replace('_', ' ', $run->status)),
                'Score' => $run->score !== null ? $run->score.' ('.$run->band.')' : '—',
                'Submitted' => $run->created_at?->format('Y-m-d H:i:s'),
                'Opened' => $run->opened_at?->format('Y-m-d H:i:s') ?? 'never opened',
                'Expires' => $run->expires_at?->format('Y-m-d H:i:s') ?? 'never (opened)',
                'Completed' => $run->completed_at?->format('Y-m-d H:i:s') ?? '—',
                'Purge after' => $run->purge_after?->format('Y-m-d') ?? '—',
                'Views' => $run->view_count,
                'AI calls' => $run->ai_calls,
                'Tokens' => number_format((int) $run->ai_tokens_total),
                'Total time' => $run->duration_ms !== null ? number_format($run->duration_ms).' ms' : '—',
            ] as $label => $value)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($run->failure_code)
            <div class="mt-3 rounded-lg bg-danger-50 p-3 dark:bg-danger-500/10">
                <p class="font-medium text-danger-700 dark:text-danger-400">
                    Failed at {{ $run->failed_stage }} — {{ $run->failure_code }}
                </p>
                <p class="mt-1 whitespace-pre-wrap break-words text-danger-700 dark:text-danger-400">{{ $run->failure_message }}</p>
            </div>
        @endif
    </div>

    {{-- Who submitted it, and the signals behind them --}}
    <div>
        <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Submitter</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
            @foreach ([
                'Email' => $run->email,
                'Canonical' => $run->email_canonical,
                'Charged to' => $run->quota_email,
                'Marketing opt-in' => $run->marketing_opt_in ? 'yes' : 'no',
                'Consented at' => $run->consent_at?->format('Y-m-d H:i:s') ?? '—',
                'IP' => $run->ip ?? '—',
                'Device' => $run->device_hash ? substr($run->device_hash, 0, 16).'…' : 'no cookie',
                'Locale' => $run->locale ?? '—',
                'Referrer' => $run->referrer ?: '—',
            ] as $label => $value)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($run->quota_email !== $run->email_canonical)
            <p class="mt-2 text-warning-700 dark:text-warning-400">
                This submission reused data that another address had already checked that day, so it spent
                <strong>{{ $run->quota_email }}</strong>'s daily allowance rather than its own.
            </p>
        @endif

        @if ($run->user_agent)
            <p class="mt-2 break-words text-xs text-gray-500 dark:text-gray-400">{{ $run->user_agent }}</p>
        @endif
    </div>

    {{-- The uploaded file --}}
    @if ($file)
        <div>
            <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Uploaded file</h3>
            <p class="text-gray-950 dark:text-white">
                {{ $file->original_name }}
                <span class="text-gray-500 dark:text-gray-400">
                    · {{ $file->mime }} · {{ number_format($file->size_bytes / 1024, 1) }} KB
                    · {{ $file->extracted_chars !== null ? number_format($file->extracted_chars).' chars extracted' : 'not extracted' }}
                </span>
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $file->disk }}:{{ $file->path }}
                @unless (\Illuminate\Support\Facades\Storage::disk($file->disk)->exists($file->path))
                    <span class="font-medium text-danger-600 dark:text-danger-400">— missing on disk</span>
                @endunless
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use the row menu to download it.</p>
        </div>
    @endif

    {{-- The job, exactly as submitted --}}
    <div>
        <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Job submitted</h3>
        <pre class="max-h-64 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>

    {{-- What the AI produced --}}
    <div>
        <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">
            Report
            @if ($candidate)
                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $candidate['fullName'] ?? 'unnamed candidate' }}</span>
            @endif
        </h3>
        @if ($report)
            <pre class="max-h-80 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @else
            <p class="text-gray-500 dark:text-gray-400">Nothing produced yet.</p>
        @endif
    </div>

    {{-- Stage by stage: the run's own history, including retries --}}
    <div>
        <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Stages</h3>
        @if ($events->isEmpty())
            <p class="text-gray-500 dark:text-gray-400">No stage has run yet.</p>
        @else
            <table class="w-full text-left text-xs">
                <thead class="text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-1 pr-3 font-medium">Stage</th>
                        <th class="py-1 pr-3 font-medium">Try</th>
                        <th class="py-1 pr-3 font-medium">Result</th>
                        <th class="py-1 pr-3 font-medium">Provider / model</th>
                        <th class="py-1 pr-3 font-medium">Tokens</th>
                        <th class="py-1 pr-3 font-medium">Time</th>
                        <th class="py-1 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($events as $event)
                        <tr>
                            <td class="py-1.5 pr-3 text-gray-950 dark:text-white">{{ $event->stage }}</td>
                            <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ $event->attempt }}</td>
                            <td class="py-1.5 pr-3 {{ $event->status === \App\Models\ToolRunEvent::STATUS_SUCCESS ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $event->status }}
                            </td>
                            <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">
                                {{ $event->provider ?? '—' }}@if ($event->model) · {{ $event->model }}@endif
                                @unless ($event->provider_inherited)
                                    <span class="text-warning-600 dark:text-warning-400">(override)</span>
                                @endunless
                            </td>
                            <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ $event->tokens_total !== null ? number_format($event->tokens_total) : '—' }}</td>
                            <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ $event->duration_ms !== null ? number_format($event->duration_ms).' ms' : '—' }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $event->created_at?->format('H:i:s') }}</td>
                        </tr>
                        @if ($event->error_message)
                            <tr>
                                <td colspan="7" class="pb-2 text-danger-600 dark:text-danger-400">{{ $event->error_message }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Full request and response payloads are in Tool Usage Logs.
            </p>
        @endif
    </div>
</div>
