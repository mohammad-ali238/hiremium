<x-app-layout :title="'Dashboard'">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Dashboard</h1>
                <p class="text-sm text-slate-500 mt-1">Plan, AI usage, and your API key.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('billing_status'))
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('billing_status') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif

            @php
                // Subscription management state. A subscription can only be managed
                // in-app when it is active, still valid, and backed by an external
                // payment-provider subscription (manually-assigned plans have no provider id).
                $isManageable = $subscription
                    && $subscription->status === \App\Models\Subscription::STATUS_ACTIVE
                    && $subscription->ends_at->isFuture()
                    && ! empty($subscription->lemonsqueezy_subscription_id);
                $isCancelled = $isManageable && $subscription->lemonsqueezy_status === 'cancelled';
                $isPastDue = $isManageable && $subscription->lemonsqueezy_status === 'past_due';
            @endphp

            @if ($isPastDue)
                <div class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-4 sm:flex sm:items-center sm:justify-between gap-4">
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 shrink-0 text-rose-600 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <div class="text-sm text-rose-800">
                            <p class="font-semibold">We couldn't process your last payment</p>
                            <p class="mt-0.5 text-rose-700">
                                Please update your payment method to avoid losing access on
                                <span class="font-medium">{{ $subscription->ends_at->toFormattedDayDateString() }}</span>.
                            </p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('subscription.payment-method') }}" class="mt-3 sm:mt-0 shrink-0">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-md">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                            Update payment method
                        </button>
                    </form>
                </div>
            @endif

            {{-- Plan & credits --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Current plan</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ $subscription?->plan?->name ?? 'No plan assigned' }}
                    </div>
                    @if ($subscription)
                        <div class="text-xs text-gray-500 mt-1">
                            Status: <span class="font-medium">{{ $subscription->status }}</span>
                            <span class="ml-1">&middot; Billed {{ $subscription->billing_interval === 'yearly' ? 'yearly' : 'monthly' }}</span>
                        </div>
                        @if ($isPastDue)
                            <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-rose-200">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                Past due
                            </div>
                        @elseif ($isCancelled)
                            <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                Won't renew
                            </div>
                        @elseif ($isManageable && $subscription->renews_at)
                            <div class="text-xs text-gray-500 mt-1">
                                Renews on {{ $subscription->renews_at->toFormattedDayDateString() }}
                            </div>
                        @endif
                    @endif
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">AI usage remaining</div>
                    @php
                        // The allotment stored on the user's own subscription
                        // (snapshot for the purchased interval), not the plan table.
                        $limit = $subscription?->creditsTotal() ?? 0;
                        $remaining = $subscription?->credits_remaining ?? 0;
                        $used = $subscription?->creditsUsed() ?? 0;
                        $percent = $limit > 0 ? min(100, max(0, round(($remaining / $limit) * 100))) : 0;
                    @endphp
                    <div class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ number_format($remaining) }} <span class="text-base text-gray-500">/ {{ number_format($limit) }}</span>
                    </div>
                    <div class="mt-3 h-2 bg-gray-200 rounded">
                        <div class="h-2 bg-indigo-500 rounded" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ number_format($used) }} used</div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Validity</div>
                    @if ($subscription)
                        <div class="mt-2 text-2xl font-semibold text-gray-900">
                            {{ $subscription->ends_at->isFuture() ? (int) $subscription->ends_at->diffInDays(now(), true) . ' days left' : 'Expired' }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Until {{ $subscription->ends_at->toDayDateTimeString() }}
                        </div>
                    @else
                        <div class="mt-2 text-2xl font-semibold text-gray-400">—</div>
                        <div class="text-xs text-gray-500 mt-1">Contact admin to get a plan</div>
                    @endif
                </div>
            </div>

            {{-- Manage subscription --}}
            @if ($isManageable)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Manage subscription</h3>
                            @if ($isCancelled)
                                <p class="text-sm text-gray-600 mt-1">
                                    Your <span class="font-medium">{{ $subscription->plan?->name }}</span> plan is cancelled.
                                    You keep access and your remaining AI usage until
                                    <span class="font-medium">{{ $subscription->ends_at->toFormattedDayDateString() }}</span>,
                                    after which it will not renew. You can resume anytime before then.
                                </p>
                            @else
                                <p class="text-sm text-gray-600 mt-1">
                                    Cancel anytime — you keep your plan and remaining AI usage until the end of the
                                    current billing period. No refunds are issued for the current period.
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0">
                            @if ($isCancelled)
                                <form method="POST" action="{{ route('subscription.resume') }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992M4.354 9.348a8.25 8.25 0 0113.803-3.7l3.181 3.182m0 0V4.5m0 4.33h-4.33M19.646 14.652a8.25 8.25 0 01-13.803 3.7L2.66 15.168m0 0v4.33m0-4.33h4.33"/></svg>
                                        Resume subscription
                                    </button>
                                </form>
                            @else
                                <button type="button" data-open-cancel-modal
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-white ring-1 ring-inset ring-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-medium rounded-md">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Cancel subscription
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                @unless ($isCancelled)
                    {{-- Custom cancel-confirmation modal --}}
                    <div id="cancel-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title">
                        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" data-close-cancel-modal></div>
                        <div class="relative flex min-h-full items-center justify-center p-4">
                            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-6 sm:p-7">
                                <button type="button" data-close-cancel-modal
                                        class="absolute top-4 right-4 text-slate-400 hover:text-slate-600" aria-label="Close">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>

                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                </div>

                                <h3 id="cancel-modal-title" class="mt-4 text-lg font-semibold text-slate-900">Cancel your subscription?</h3>
                                <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                                    You'll keep your <span class="font-medium text-slate-800">{{ $subscription->plan?->name }}</span> plan and your
                                    <span class="font-medium text-slate-800">{{ number_format($subscription->credits_remaining) }}</span> remaining credits until
                                    <span class="font-medium text-slate-800">{{ $subscription->ends_at->toFormattedDayDateString() }}</span>.
                                    After that, it won't renew and you'll lose API access. No refund is issued for the current period.
                                    You can resume anytime before it ends.
                                </p>

                                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                                    <button type="button" data-close-cancel-modal
                                            class="inline-flex justify-center items-center px-4 py-2 bg-white ring-1 ring-inset ring-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-medium rounded-md">
                                        Keep subscription
                                    </button>
                                    <form method="POST" action="{{ route('subscription.cancel') }}">
                                        @csrf
                                        <button type="submit"
                                                class="w-full inline-flex justify-center items-center px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-md">
                                            Yes, cancel it
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        (function () {
                            var modal = document.getElementById('cancel-modal');
                            if (!modal) return;
                            function open() { modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
                            function close() { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }
                            document.querySelectorAll('[data-open-cancel-modal]').forEach(function (b) { b.addEventListener('click', open); });
                            modal.querySelectorAll('[data-close-cancel-modal]').forEach(function (b) { b.addEventListener('click', close); });
                            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
                        })();
                    </script>
                @endunless
            @endif

            {{-- API key card --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">API key</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Send this in the <code class="bg-gray-100 px-1 rounded">Authorization: Bearer &lt;key&gt;</code> header.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('api-token.store') }}">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                                {{ $token ? 'Regenerate' : 'Generate' }}
                            </button>
                        </form>
                        @if ($token)
                            <form method="POST" action="{{ route('api-token.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md">
                                    Revoke
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($plainTextToken)
                    <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded">
                        <div class="text-xs font-semibold text-amber-800 mb-2">
                            Copy your key now — it will not be shown again.
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="api-token-input" type="text" readonly value="{{ $plainTextToken }}"
                                   class="flex-1 font-mono text-xs bg-white border border-amber-300 rounded px-3 py-2" />
                            <button type="button" data-copy-target="api-token-input"
                                    class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium rounded">
                                Copy
                            </button>
                        </div>
                    </div>
                    <script>
                        document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var input = document.getElementById(btn.dataset.copyTarget);
                                if (!input) return;
                                input.focus();
                                input.select();
                                input.setSelectionRange(0, input.value.length);
                                var copied = false;
                                try {
                                    if (navigator.clipboard && window.isSecureContext) {
                                        navigator.clipboard.writeText(input.value);
                                        copied = true;
                                    } else {
                                        copied = document.execCommand('copy');
                                    }
                                } catch (e) {
                                    copied = false;
                                }
                                btn.textContent = copied ? 'Copied!' : 'Press Ctrl+C';
                            });
                        });
                    </script>
                @elseif ($token)
                    <div class="mt-4 text-sm text-gray-600">
                        Active key — created {{ $token->created_at->diffForHumans() }}.
                        Last used: {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'never' }}.
                        <span class="text-xs text-gray-500">(plain key shown only once at creation)</span>
                    </div>
                @else
                    <div class="mt-4 text-sm text-gray-500">No API key generated yet.</div>
                @endif
            </div>

            {{-- Usage log --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Usage log</h3>
                @if ($usageLogs->isEmpty())
                    <p class="text-sm text-gray-500">No API calls yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($usageLogs as $log)
                            @php
                                $isScore = str_starts_with($log->endpoint, 'matching-score');
                                $isParse = str_starts_with($log->endpoint, 'resume-parse');
                                $typeLabel = $isScore ? 'Match Score' : ($isParse ? 'Resume Parsing' : $log->endpoint);
                            @endphp
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl ring-1 ring-slate-200 hover:ring-indigo-200 hover:bg-slate-50/60 transition px-4 py-3">

                                {{-- Call-type icon --}}
                                <span @class([
                                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                    'bg-indigo-50 text-indigo-600' => $isScore,
                                    'bg-violet-50 text-violet-600' => $isParse,
                                    'bg-slate-100 text-slate-500' => ! $isScore && ! $isParse,
                                ])>
                                    @if ($isScore)
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/>
                                        </svg>
                                    @elseif ($isParse)
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zM9 12.75h6m-6 3h6"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25"/>
                                        </svg>
                                    @endif
                                </span>

                                {{-- Type + date --}}
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-slate-900">{{ $typeLabel }}</div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                        <span>{{ $log->created_at->format('M j, Y · H:i') }}</span>
                                        <span class="hidden sm:inline text-slate-300">&middot;</span>
                                        <span class="hidden sm:inline font-mono">{{ $log->endpoint }}</span>
                                    </div>
                                </div>

                                {{-- Score (match-score calls only) --}}
                                @if ($isScore && $log->score !== null)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100">
                                        Score {{ $log->score }}<span class="font-normal text-indigo-400">/100</span>
                                    </span>
                                @endif

                                {{-- Status --}}
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                    'bg-green-100 text-green-800' => $log->status === 'success',
                                    'bg-yellow-100 text-yellow-800' => $log->status === 'refunded',
                                    'bg-red-100 text-red-800' => $log->status === 'failed',
                                ])>{{ $log->status }}</span>

                                {{-- Credits --}}
                                <span class="inline-flex items-center gap-1 text-sm font-semibold text-slate-700 tabular-nums">
                                    {{ $log->credits_consumed }}
                                    <span class="text-xs font-normal text-slate-400">{{ $log->credits_consumed === 1 ? 'credit' : 'credits' }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">{{ $usageLogs->links() }}</div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
