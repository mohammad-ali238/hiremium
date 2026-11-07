@php
    $monthlyPct = $monthlyTotal > 0 ? (int) round($monthlyUsed / $monthlyTotal * 100) : 0;
    $seatPct = $maxSeats > 0 ? (int) round(min($seatsUsed, $maxSeats) / $maxSeats * 100) : 0;
    $jobPct = $maxJobs > 0 ? (int) round(min($jobsUsed, $maxJobs) / $maxJobs * 100) : 0;
@endphp
<div class="space-y-6">
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('app.dashboard')], ['label' => 'AI Usage']]" class="mb-2" />
        <h1 class="text-2xl font-bold text-app-text">AI Usage</h1>
        <p class="mt-1 text-sm text-muted">Your plan, AI usage and workspace limits.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Plan --}}
        <x-ui.card title="Current plan">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-app-text">{{ $planName }}</p>
                    <p class="text-xs text-muted">Renews / resets {{ $resetAt?->diffForHumans() ?? 'monthly' }}</p>
                </div>
                <x-ui.badge color="primary">Active</x-ui.badge>
            </div>
            <a href="{{ route('pricing') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">Upgrade plan →</a>
        </x-ui.card>

        {{-- Credits --}}
        <x-ui.card title="AI Usage" class="lg:col-span-2">
            <div class="grid gap-6 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted">Total available</p>
                    <p class="text-3xl font-bold tabular-nums text-app-text">{{ number_format($totalBalance) }}</p>
                </div>
                <div class="sm:col-span-2">
                    <x-ui.progress :value="$monthlyPct"
                                   :label="'Monthly · '.number_format($monthlyRemaining).' of '.number_format($monthlyTotal).' left'"
                                   :show-value="true" />
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-muted">Top-up packs</span>
                        <span class="font-medium tabular-nums text-app-text">{{ number_format($packBalance) }} AI requests</span>
                    </div>
                    <p class="mt-2 text-xs text-muted">Monthly AI usage is spent first, then top-up packs.</p>
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- Limits --}}
    <x-ui.card title="Workspace limits">
        <div class="grid gap-6 sm:grid-cols-3">
            <div>
                <x-ui.progress :value="$seatPct" :label="'Team seats'" />
                <p class="mt-1 text-sm font-medium tabular-nums text-app-text">{{ $seatsUsed }} / {{ $maxSeats }}</p>
            </div>
            <div>
                <x-ui.progress :value="$jobPct" :label="'Jobs'" />
                <p class="mt-1 text-sm font-medium tabular-nums text-app-text">{{ $jobsUsed }} / {{ $maxJobs }}</p>
            </div>
            <div>
                <p class="text-xs text-muted">CVs per upload</p>
                <p class="mt-1 text-sm font-medium tabular-nums text-app-text">up to {{ $maxFilesPerBatch }}</p>
            </div>
        </div>
    </x-ui.card>

    {{-- Credit usage per resume batch --}}
    <x-ui.card title="AI usage by batch" subtitle="AI usage consumed by analysis, per resume upload." :padding="false">
        @if ($batchUsage->isEmpty())
            <x-ui.empty title="No usage yet" description="Upload CVs to a job and the AI usage will appear here." />
        @else
            <x-ui.table class="rounded-none border-0">
                <thead>
                    <tr>
                        <x-ui.th>Batch</x-ui.th>
                        <x-ui.th>Job</x-ui.th>
                        <x-ui.th>Files</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th class="text-right">AI usage</x-ui.th>
                        <x-ui.th class="text-right">Date</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($batchUsage as $b)
                        <tr>
                            <x-ui.td>
                                @if ($b->trashed())
                                    <span class="font-medium text-muted">#{{ $b->id }}</span>
                                    <x-ui.badge color="gray" class="ml-1">Deleted</x-ui.badge>
                                @else
                                    <a href="{{ route('app.batches.show', $b) }}" class="font-medium text-app-text hover:text-primary" wire:navigate>#{{ $b->id }}</a>
                                @endif
                            </x-ui.td>
                            <x-ui.td class="text-muted">
                                {{ $b->job_snapshot['title'] ?? $b->job?->title ?? '—' }}
                                @if ($b->job?->trashed()) <span class="text-xs">(deleted)</span> @endif
                            </x-ui.td>
                            <x-ui.td class="tabular-nums">{{ $b->total_files }}</x-ui.td>
                            <x-ui.td>
                                <x-ui.batch-status :status="$b->status" size="sm" />
                            </x-ui.td>
                            <x-ui.td class="text-right font-medium tabular-nums">{{ (int) ($b->credits_used ?? 0) }}</x-ui.td>
                            <x-ui.td class="text-right text-muted">{{ $b->created_at?->diffForHumans() }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    {{-- Top-up packs --}}
    <x-ui.card title="Buy AI usage packs" subtitle="Top-up AI usage never expires and is used after your monthly allowance.">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($packs as $pack)
                <div class="rounded-xl border border-line p-5 text-center">
                    <p class="text-lg font-bold text-app-text">{{ $pack->name }}</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-primary">{{ number_format($pack->credits) }}</p>
                    <p class="text-xs text-muted">AI requests</p>
                    <p class="mt-3 text-sm font-medium text-app-text">${{ number_format($pack->price, 2) }}</p>
                    @if ($canBuy)
                        <x-ui.button variant="secondary" size="sm" class="mt-4 w-full" wire:click="buy({{ $pack->id }})">Buy pack</x-ui.button>
                    @endif
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
