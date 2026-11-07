<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-app-text">API</h1>
        <p class="mt-1 text-sm text-muted">Generate an API key to use the matching &amp; parsing endpoints, and review usage.</p>
    </div>

    <x-ui.card title="API key">
        @if ($plainTextToken)
            <div class="rounded-lg border border-success/40 bg-success/10 p-4">
                <p class="text-sm font-medium text-app-text">Your new API key — copy it now, it won't be shown again.</p>
                <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                    <code class="flex-1 overflow-x-auto rounded-md bg-surface px-3 py-2 text-xs text-app-text">{{ $plainTextToken }}</code>
                    <x-ui.button size="sm" variant="secondary"
                                 x-on:click="navigator.clipboard.writeText(@js($plainTextToken)); copied = true; setTimeout(() => copied = false, 1500)">
                        <span x-show="! copied">Copy</span><span x-show="copied" x-cloak>Copied ✓</span>
                    </x-ui.button>
                </div>
            </div>
            <div class="mt-4">
                <x-ui.button variant="secondary" size="sm" wire:click="generate">Regenerate</x-ui.button>
            </div>
        @elseif ($token)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-app-text">Active key</p>
                    <p class="text-xs text-muted">
                        Created {{ $token->created_at?->diffForHumans() }} ·
                        Last used {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'never' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button size="sm" wire:click="generate">Regenerate</x-ui.button>
                    <x-ui.button size="sm" variant="ghost"
                                 x-on:click="$store.confirm.ask({ title: 'Revoke API key?', text: 'Apps using this key will stop working.', danger: true, confirmText: 'Revoke' }).then(ok => ok && $wire.revoke())">
                        Revoke
                    </x-ui.button>
                </div>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted">No API key yet.</p>
                <x-ui.button wire:click="generate">Generate API key</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="API usage" :padding="false">
        @if ($logs->isEmpty())
            <x-ui.empty title="No API usage yet" description="Requests made with your key will be logged here." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead>
                    <tr>
                        <x-ui.th>Endpoint</x-ui.th>
                        <x-ui.th>AI usage</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th>When</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($logs as $log)
                        <tr>
                            <x-ui.td class="text-app-text">{{ $log->endpoint }}</x-ui.td>
                            <x-ui.td class="tabular-nums text-muted">{{ $log->credits_consumed }}</x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :color="$log->status === 'success' ? 'success' : ($log->status === 'refunded' ? 'warning' : 'danger')">{{ ucfirst($log->status) }}</x-ui.badge>
                            </x-ui.td>
                            <x-ui.td class="text-muted">{{ $log->created_at?->diffForHumans() }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($logs->hasPages())<div>{{ $logs->links() }}</div>@endif
</div>
