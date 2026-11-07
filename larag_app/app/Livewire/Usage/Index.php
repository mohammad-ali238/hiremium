<?php

namespace App\Livewire\Usage;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\ResumeBatch;
use App\Models\TopUpPack;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\WorkspaceCredits;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.dashboard')]
#[Title('Usage & Credits')]
class Index extends Component
{
    use InteractsWithWorkspace;

    /** Placeholder checkout — real LemonSqueezy pack checkout lands with billing. */
    public function buy(int $packId): void
    {
        abort_unless(Auth::user()->can('billing.manage'), 403);

        $this->dispatch('toast', type: 'info', message: 'Pack checkout is coming soon.');
    }

    public function render()
    {
        $workspace = $this->currentWorkspace();
        $limits = app(PlanLimits::class);
        $credits = app(WorkspaceCredits::class);

        $plan = $limits->planFor($workspace);
        $subscription = $workspace->ownerSubscription();
        $balance = $credits->balance($workspace);
        // The user's stored allotment for this period, not the plan's current config.
        $monthlyTotal = (int) ($subscription?->creditsTotal() ?? 0);

        return view('livewire.usage.index', [
            'planName' => $plan?->name ?? 'Free',
            'monthlyTotal' => $monthlyTotal,
            'monthlyRemaining' => $balance['monthly'],
            'monthlyUsed' => max(0, $monthlyTotal - $balance['monthly']),
            'packBalance' => $balance['packs'],
            'totalBalance' => $balance['total'],
            'seatsUsed' => $limits->seatsUsed($workspace),
            'maxSeats' => $limits->maxSeats($workspace),
            'jobsUsed' => $limits->jobsUsed($workspace),
            'maxJobs' => $limits->maxJobs($workspace),
            'maxFilesPerBatch' => $limits->maxFilesPerBatch($workspace),
            // Credits refresh when the period rolls over (free) or renews (paid).
            'resetAt' => $subscription?->credits_reset_at ?? $subscription?->ends_at,
            'canBuy' => Auth::user()->can('billing.manage'),
            'packs' => TopUpPack::active()->orderBy('credits')->get(),
            // Includes batches (and jobs) the user has deleted — spent AI usage must
            // never disappear from their own history.
            'batchUsage' => ResumeBatch::forCurrentWorkspace()
                ->withTrashed()
                ->withSum('usageLogs as credits_used', 'credits_charged')
                ->with('job:id,title,deleted_at')
                ->latest('id')
                ->limit(12)
                ->get(),
        ]);
    }
}
