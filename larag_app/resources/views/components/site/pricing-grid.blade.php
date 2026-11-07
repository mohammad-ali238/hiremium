@props([
    'plans',
    'parsingCosts' => [],
    'currentSubscription' => null,
    'billingEnabled' => false,
])

@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

    $cur = $currentSubscription;
    $curTier = $cur?->plan?->credit_limit ?? -1;
    $curInterval = $cur ? \App\Models\Plan::normalizeInterval($cur->billing_interval) : '';
    $curPlanId = (int) ($cur?->plan_id ?? 0);

    $anyYearly = $plans->contains(fn ($p) => $p->yearlyAvailable());
    $maxSavings = (int) ($plans->map(fn ($p) => $p->yearlySavingsPercent() ?? 0)->max() ?? 0);
    // Monthly is always the default on page load.
    $defaultInterval = 'monthly';
    $batchDefault = (int) config('hiremium.ai.max_cvs_per_batch', 25);

    $btn = 'inline-flex w-full items-center justify-center rounded-md px-5 py-2.5 text-sm font-semibold transition';
    $disabled = 'bg-slate-100 text-slate-400 ring-1 ring-slate-200 cursor-not-allowed';
@endphp

<div
    x-data="{
        interval: @js($defaultInterval),
        cur: { tier: {{ (int) $curTier }}, interval: @js($curInterval), planId: {{ $curPlanId }} },
        cta(planId, tier, hasYearly, name) {
            const iv = this.interval;
            if (iv === 'yearly' && !hasYearly) return { kind: 'disabled', label: 'Yearly not available' };
            if (this.cur.tier < 0) return { kind: 'buy', label: 'Choose ' + name };
            if (tier > this.cur.tier) return { kind: 'upgrade', label: 'Upgrade to ' + name };
            if (tier === this.cur.tier) {
                if (planId === this.cur.planId && iv === this.cur.interval) return { kind: 'current', label: 'Current plan' };
                if (iv === 'yearly' && this.cur.interval === 'monthly') return { kind: 'upgrade', label: 'Switch to yearly' };
                return { kind: 'blocked', label: 'Included in your plan' };
            }
            return { kind: 'blocked', label: 'Lower plan' };
        }
    }"
>
    @if ($anyYearly)
        {{-- Billing-interval toggle --}}
        <div class="flex items-center justify-center gap-3 mb-12">
            <span class="text-sm" :class="interval === 'monthly' ? 'text-slate-900 font-semibold' : 'text-slate-500'">Monthly</span>
            <button type="button" role="switch" :aria-checked="interval === 'yearly'"
                    @click="interval = (interval === 'monthly' ? 'yearly' : 'monthly')"
                    class="relative inline-flex h-7 w-12 items-center rounded-full bg-indigo-600 transition">
                <span class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                      :class="interval === 'yearly' ? 'translate-x-5' : 'translate-x-0.5'"></span>
            </button>
            <span class="text-sm" :class="interval === 'yearly' ? 'text-slate-900 font-semibold' : 'text-slate-500'">
                Yearly
                @if ($maxSavings > 0)
                    <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold">Save up to {{ $maxSavings }}%</span>
                @endif
            </span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @forelse ($plans as $i => $plan)
            @php
                $highlight = $plan->name === 'Pro';
                $primary = $highlight ? 'bg-white text-slate-900 hover:bg-slate-100' : 'bg-indigo-600 text-white hover:bg-indigo-700';
                $muted = $highlight ? 'text-slate-300' : 'text-slate-500';
                $savings = $plan->yearlySavingsPercent();
                $yearlyCredits = $plan->creditsFor('yearly');
                $batchFiles = $plan->max_files_per_batch ?? $batchDefault;
            @endphp
            <div @class([
                'rounded-2xl p-8 flex flex-col',
                'bg-slate-900 text-white shadow-xl ring-1 ring-slate-900' => $highlight,
                'bg-white ring-1 ring-slate-200' => ! $highlight,
            ])>
                @if ($highlight)
                    <span class="self-start inline-flex items-center px-2.5 py-0.5 rounded-full bg-indigo-500 text-white text-xs font-semibold">Most popular</span>
                @endif
                <h3 @class(['mt-3 text-xl font-semibold', 'text-white' => $highlight, 'text-slate-900' => ! $highlight])>{{ $plan->name }}</h3>
                <p class="mt-1 text-sm {{ $muted }}">{{ $plan->description ?? $plan->name.' plan' }}</p>

                {{-- Price (interval-aware) --}}
                <div class="mt-6 min-h-[3rem]">
                    <div x-show="interval === 'monthly'" class="flex items-baseline gap-1">
                        <span @class(['text-4xl font-extrabold tracking-tight', 'text-white' => $highlight, 'text-slate-900' => ! $highlight])>${{ $fmt($plan->price) }}</span>
                        <span class="text-sm {{ $muted }}">/ mo</span>
                    </div>
                    <div x-show="interval === 'yearly'" x-cloak class="flex items-baseline gap-2 flex-wrap">
                        @if ($plan->yearlyAvailable())
                            <span @class(['text-4xl font-extrabold tracking-tight', 'text-white' => $highlight, 'text-slate-900' => ! $highlight])>${{ $fmt($plan->yearly_price) }}</span>
                            <span class="text-sm {{ $muted }}">/ yr</span>
                            @if ($savings)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold">Save {{ $savings }}%</span>
                            @endif
                        @else
                            <span @class(['text-4xl font-extrabold tracking-tight', 'text-white' => $highlight, 'text-slate-900' => ! $highlight])>${{ $fmt($plan->price) }}</span>
                            <span class="text-sm {{ $muted }}">/ mo &middot; monthly only</span>
                        @endif
                    </div>
                </div>

                <ul @class(['mt-6 space-y-3 text-sm flex-1', 'text-slate-200' => $highlight, 'text-slate-700' => ! $highlight])>
                    <li class="flex gap-2">
                        <span class="text-emerald-400">&#10003;</span>
                        <span>
                            <span x-show="interval === 'monthly'"><strong>{{ number_format($plan->credit_limit) }}</strong> AI Requests / month</span>
                            <span x-show="interval === 'yearly'" x-cloak><strong>{{ number_format($plan->yearlyAvailable() ? $yearlyCredits : $plan->credit_limit) }}</strong> AI Requests{{ $plan->yearlyAvailable() ? ' / year' : ' / month' }}</span>
                        </span>
                    </li>
                    <li class="flex gap-2"><span class="text-emerald-400">&#10003;</span> <span>Up to <strong>{{ number_format($batchFiles) }}</strong> CVs per batch</span></li>
                    <li class="flex gap-2"><span class="text-emerald-400">&#10003;</span> 1 AI Request used per Analyze</li>
                    <li class="flex gap-2"><span class="text-emerald-400">&#10003;</span> Bulk file &amp; ZIP upload</li>
                    <li class="flex gap-2"><span class="text-emerald-400">&#10003;</span> Auto-ranked candidate shortlists</li>
                    <li class="flex gap-2"><span class="text-emerald-400">&#10003;</span> Per-candidate match report</li>
                    <li class="flex gap-2"><span class="text-emerald-400">&#10003;</span> Strengths &amp; concerns per candidate</li>
                </ul>

                {{-- CTA --}}
                @guest
                    <a href="{{ route('register') }}" class="mt-8 {{ $btn }} {{ $primary }}">Choose {{ $plan->name }}</a>
                @else
                    @if (! $billingEnabled)
                        <a href="{{ route('contact') }}" class="mt-8 {{ $btn }} {{ $primary }}">Contact us</a>
                    @else
                        <div class="mt-8"
                             x-data="{ p: { id: {{ $plan->id }}, tier: {{ (int) $plan->credit_limit }}, hasYearly: {{ $plan->yearlyAvailable() ? 'true' : 'false' }}, name: @js($plan->name) } }">
                            <template x-if="['buy','upgrade'].includes(cta(p.id, p.tier, p.hasYearly, p.name).kind)">
                                <form method="POST" action="{{ route('checkout.start') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <input type="hidden" name="interval" :value="interval">
                                    <button type="submit" class="{{ $btn }} {{ $primary }}" x-text="cta(p.id, p.tier, p.hasYearly, p.name).label"></button>
                                </form>
                            </template>
                            <template x-if="!['buy','upgrade'].includes(cta(p.id, p.tier, p.hasYearly, p.name).kind)">
                                <span class="{{ $btn }} {{ $disabled }}" x-text="cta(p.id, p.tier, p.hasYearly, p.name).label"></span>
                            </template>
                        </div>
                    @endif
                @endguest
            </div>
        @empty
            <div class="md:col-span-2 lg:col-span-4 text-center text-slate-500 py-16">No plans available yet.</div>
        @endforelse
    </div>
</div>
