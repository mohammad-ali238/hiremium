<x-app-layout :title="'My Orders'">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">My Orders</h1>
                <p class="text-sm text-slate-500 mt-1">Your billing history — purchases, renewals, and changes.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($orders->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-sm text-gray-500">You have no orders yet.</p>
                        <a href="{{ route('pricing') }}" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">
                            View plans
                        </a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($orders as $order)
                            @php
                                $label = match ($order->event_name) {
                                    'subscription_created' => 'Subscription started',
                                    'subscription_payment_success' => 'Payment',
                                    'subscription_updated' => 'Plan changed',
                                    'subscription_cancelled' => 'Cancelled',
                                    'subscription_expired' => 'Expired',
                                    default => ucfirst(str_replace(['subscription_', '_'], ['', ' '], (string) $order->event_name)),
                                };
                                $isCharge = in_array($order->event_name, ['subscription_created', 'subscription_payment_success'], true);
                            @endphp
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl ring-1 ring-slate-200 hover:ring-indigo-200 hover:bg-slate-50/60 transition px-4 py-3">

                                {{-- Icon --}}
                                <span @class([
                                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                    'bg-emerald-50 text-emerald-600' => $isCharge,
                                    'bg-slate-100 text-slate-500' => ! $isCharge,
                                ])>
                                    @if ($isCharge)
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                                    @else
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                </span>

                                {{-- Detail --}}
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $label }}
                                        @if ($order->plan)
                                            <span class="text-slate-400 font-normal">&middot; {{ $order->plan->name }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                        <span>{{ $order->created_at->format('M j, Y · H:i') }}</span>
                                        @if ($order->lemonsqueezy_order_id)
                                            <span class="hidden sm:inline text-slate-300">&middot;</span>
                                            <span class="hidden sm:inline font-mono">Order {{ $order->lemonsqueezy_order_id }}</span>
                                        @endif
                                    </div>
                                    @if ($order->cancelled_at)
                                        <div class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-amber-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Cancelled on {{ $order->cancelled_at->format('M j, Y') }}
                                        </div>
                                    @endif
                                </div>

                                {{-- Amount --}}
                                <div class="text-sm font-semibold text-slate-700 tabular-nums">
                                    @if ($order->amount_total !== null)
                                        {{ number_format($order->amount_total / 100, 2) }} {{ strtoupper((string) $order->currency) }}
                                    @else
                                        <span class="text-slate-400 font-normal">—</span>
                                    @endif
                                </div>

                                {{-- Status --}}
                                @if ($order->status)
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                        'bg-green-100 text-green-800' => in_array($order->status, ['active', 'paid'], true),
                                        'bg-yellow-100 text-yellow-800' => in_array($order->status, ['cancelled', 'past_due', 'paused'], true),
                                        'bg-red-100 text-red-800' => in_array($order->status, ['expired', 'unpaid'], true),
                                        'bg-slate-100 text-slate-700' => ! in_array($order->status, ['active', 'paid', 'cancelled', 'past_due', 'paused', 'expired', 'unpaid'], true),
                                    ])>{{ str_replace('_', ' ', $order->status) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $orders->links() }}</div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
