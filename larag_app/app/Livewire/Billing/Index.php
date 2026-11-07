<?php

namespace App\Livewire\Billing;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Subscription;
use App\Models\UserPlanHistory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Billing — the account holder's plan, credits and subscription management,
 * plus their plan history (paginated over AJAX by Livewire).
 * Cancel/resume/update-payment post to the existing billing controllers.
 *
 * An invited team member has no subscription of their own (they spend the
 * owner's credits), so they are shown the workspace's plan read-only instead of
 * an empty "No plan" page that contradicts /app/usage.
 */
#[Layout('components.layouts.dashboard')]
#[Title('Billing')]
class Index extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    /** Query-string page name, so the history pager doesn't clash with others. */
    protected string $pageName = 'historyPage';

    public function mount(): void
    {
        if ($status = session('billing_status')) {
            $this->dispatch('toast', type: 'success', message: $status);
        }
        if ($error = session('error')) {
            $this->dispatch('toast', type: 'error', message: $error);
        }
    }

    public function render()
    {
        // The plan in force is the started one — a newer row may just be a plan
        // the admin scheduled for a later date.
        $own = Subscription::with('plan')->where('user_id', Auth::id())->active()->latest('id')->first()
            ?: Subscription::with('plan')->where('user_id', Auth::id())->latest('id')->first();
        $subscription = $own ?: $this->currentWorkspace()?->ownerSubscription();
        $ownsPlan = $own !== null;

        $scheduled = Subscription::with('plan')
            ->where('user_id', Auth::id())
            ->scheduled()
            ->orderBy('starts_at')
            ->first();

        return view('livewire.billing.index', [
            'subscription' => $subscription,
            'ownsPlan' => $ownsPlan,
            'scheduled' => $scheduled,
            // Only the plan holder has a history to show.
            'histories' => UserPlanHistory::query()
                ->where('user_id', Auth::id())
                ->when(! $ownsPlan, fn ($query) => $query->whereRaw('1 = 0'))
                ->latest('id')
                ->paginate(8, pageName: $this->pageName),
        ]);
    }
}
