@php
    // Only the plan holder can manage it; a team member sees the workspace's
    // plan read-only (it belongs to the account owner).
    $isManageable = $ownsPlan
        && $subscription
        && $subscription->status === \App\Models\Subscription::STATUS_ACTIVE
        && $subscription->ends_at?->isFuture()
        && ! empty($subscription->lemonsqueezy_subscription_id);
    $isCancelled = $isManageable && $subscription->lemonsqueezy_status === 'cancelled';
    $isPastDue = $isManageable && $subscription->lemonsqueezy_status === 'past_due';
    // Period ended, plan kept while we wait for the provider's renewal.
    $inGrace = $ownsPlan && $subscription && $subscription->inRenewalGrace();

    // The total comes from the user's own subscription record (snapshot taken
    // when the plan was assigned/renewed), never from the plans table.
    $limit = $subscription?->creditsTotal() ?? 0;
    $remaining = $subscription?->credits_remaining ?? 0;
    $used = $subscription?->creditsUsed() ?? 0;
    $percent = $limit > 0 ? min(100, max(0, round($remaining / $limit * 100))) : 0;
@endphp
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-app-text">Billing</h1>
        <p class="mt-1 text-sm text-muted">
            {{ $ownsPlan ? 'Your plan, AI usage and subscription.' : 'Your workspace plan and AI usage — managed by the account owner.' }}
        </p>
    </div>

    @if ($isPastDue)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger/40 bg-danger/10 px-4 py-4">
            <div class="text-sm">
                <p class="font-semibold text-app-text">We couldn't process your last payment</p>
                <p class="text-muted">Update your payment method to avoid losing access on {{ $subscription->ends_at->toFormattedDayDateString() }}.</p>
            </div>
            <form method="POST" action="{{ route('subscription.payment-method') }}">@csrf<x-ui.button variant="danger" size="sm" type="submit">Update payment method</x-ui.button></form>
        </div>
    @endif

    @if ($scheduled)
        <div class="rounded-xl border border-primary/40 bg-primary-soft px-4 py-4 text-sm">
            <p class="font-semibold text-app-text">{{ $scheduled->planName() }} starts on {{ $scheduled->starts_at->toFormattedDayDateString() }}</p>
            <p class="text-muted">Your current plan runs until then, and {{ number_format($scheduled->creditsTotal()) }} AI credits become available on that date.</p>
        </div>
    @endif

    @if ($inGrace)
        <div class="rounded-xl border border-warning/40 bg-warning/10 px-4 py-4 text-sm">
            <p class="font-semibold text-app-text">We're waiting for your renewal payment</p>
            <p class="text-muted">
                You keep the {{ $subscription->planName() }} plan and its limits until {{ $subscription->ends_at->toFormattedDayDateString() }} while the payment goes through. Nothing is downgraded in the meantime.
            </p>
        </div>
    @endif

    {{-- Plan / credits / validity --}}
    <div class="grid gap-6 md:grid-cols-3">
        <x-ui.card title="Current plan">
            <p class="text-2xl font-bold text-app-text">{{ $subscription?->plan?->name ?? 'No plan' }}</p>
            @if ($subscription)
                <p class="mt-1 text-xs text-muted">Status: <span class="font-medium">{{ $subscription->status }}</span> · Billed {{ $subscription->billing_interval === 'yearly' ? 'yearly' : 'monthly' }}</p>
                @if ($isCancelled)
                    <x-ui.badge color="warning" class="mt-2">Won't renew</x-ui.badge>
                @elseif ($isManageable && $subscription->renews_at)
                    <p class="mt-1 text-xs text-muted">Renews {{ $subscription->renews_at->toFormattedDayDateString() }}</p>
                @endif
            @endif
            <a href="{{ route('pricing') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">View plans →</a>
        </x-ui.card>

        <x-ui.card title="Plan AI usage">
            <p class="text-2xl font-bold tabular-nums text-app-text">{{ number_format($remaining) }} <span class="text-base text-muted">/ {{ number_format($limit) }}</span></p>
            <x-ui.progress :value="$percent" class="mt-3" />
            <p class="mt-1 text-xs text-muted">{{ number_format($used) }} used · see full balance in <a href="{{ route('app.usage') }}" class="text-primary hover:underline">AI Usage</a></p>
        </x-ui.card>

        <x-ui.card title="Validity">
            @if ($subscription && $subscription->plan?->isFree())
                {{-- Free runs in 30-day cycles: at the end of each one the plan is
                     re-assigned, which is what refreshes the AI usage. --}}
                <p class="text-2xl font-bold text-app-text">{{ $subscription->ends_at?->isFuture() ? (int) $subscription->ends_at->diffInDays(now(), true).' days left' : 'Renewing' }}</p>
                <p class="mt-1 text-xs text-muted">
                    Free plan — AI usage renews{{ $subscription->ends_at ? ' on '.$subscription->ends_at->toFormattedDayDateString() : ' monthly' }}
                </p>
            @elseif ($subscription)
                <p class="text-2xl font-bold text-app-text">{{ $subscription->ends_at?->isFuture() ? (int) $subscription->ends_at->diffInDays(now(), true).' days left' : 'Expired' }}</p>
                <p class="mt-1 text-xs text-muted">
                    {{ $inGrace ? 'Renewal pending — plan kept until' : 'Until' }} {{ $subscription->ends_at?->toFormattedDayDateString() }}
                </p>
            @else
                <p class="text-2xl font-bold text-muted">—</p>
                <p class="mt-1 text-xs text-muted">No active plan</p>
            @endif
        </x-ui.card>
    </div>

    {{-- Manage subscription --}}
    @if ($isManageable)
        <x-ui.card title="Manage subscription">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-muted">
                    @if ($isCancelled)
                        Your {{ $subscription->plan?->name }} plan is cancelled — you keep access and AI usage until {{ $subscription->ends_at->toFormattedDayDateString() }}. Resume anytime before then.
                    @else
                        Cancel anytime — you keep your plan and remaining AI usage until the end of the current period. No refunds for the current period.
                    @endif
                </p>
                <div class="shrink-0">
                    @if ($isCancelled)
                        <form method="POST" action="{{ route('subscription.resume') }}">@csrf<x-ui.button type="submit">Resume subscription</x-ui.button></form>
                    @else
                        <form method="POST" action="{{ route('subscription.cancel') }}" x-ref="cancelForm">@csrf</form>
                        <x-ui.button variant="secondary"
                                     x-on:click="$store.confirm.ask({ title: 'Cancel subscription?', text: 'You keep your plan and AI usage until the period ends, then it will not renew.', danger: true, confirmText: 'Yes, cancel' }).then(ok => ok && $refs.cancelForm.submit())">
                            Cancel subscription
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @endif

    {{-- Plan history (only the plan holder has one) --}}
    @if ($ownsPlan)
    <x-ui.card title="Plan history" subtitle="Every plan you have been on — assignments, renewals, cancellations and expiries." :padding="false">
        @if ($histories->isEmpty())
            <div class="p-5">
                <x-ui.empty title="No plan history yet" description="Your plan changes will be listed here." />
            </div>
        @else
            <x-ui.table class="rounded-none border-0">
                <thead>
                    <tr>
                        <x-ui.th>Date</x-ui.th>
                        <x-ui.th>Plan</x-ui.th>
                        <x-ui.th>Event</x-ui.th>
                        <x-ui.th>AI credits</x-ui.th>
                        <x-ui.th>Period</x-ui.th>
                        <x-ui.th>Details</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($histories as $history)
                        <tr wire:key="plan-history-{{ $history->id }}">
                            <x-ui.td class="whitespace-nowrap text-muted">{{ $history->created_at?->toFormattedDayDateString() }}</x-ui.td>
                            <x-ui.td class="font-medium">{{ $history->plan_name }}</x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :color="$history->eventColor()" size="sm">{{ $history->eventLabel() }}</x-ui.badge>
                            </x-ui.td>
                            <x-ui.td class="tabular-nums">{{ number_format($history->credits_total) }}</x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-muted">
                                @if ($history->starts_at)
                                    {{ $history->starts_at->format('M j, Y') }}
                                    {{-- A free plan's ends_at is a far-future sentinel — don't show it. --}}
                                    @if ($history->ends_at && $history->starts_at->diffInYears($history->ends_at) < 5)
                                        – {{ $history->ends_at->format('M j, Y') }}
                                    @endif
                                @else
                                    —
                                @endif
                            </x-ui.td>
                            <x-ui.td class="text-muted">
                                {{ $history->sourceLabel() }}
                                @if ($history->cancelled_at)
                                    · cancelled {{ $history->cancelled_at->format('M j, Y') }}
                                @endif
                                @if ($history->expired_at)
                                    · expired {{ $history->expired_at->format('M j, Y') }}
                                @endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif

        @if ($histories->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $histories->links() }}</div>
        @endif
    </x-ui.card>
    @endif
</div>
