<?php

namespace App\Livewire\Jobs;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use App\Services\Activity\ActivityLogger;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.dashboard')]
#[Title('Jobs')]
class Index extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Job::class);

        if ($toast = session('flash_toast')) {
            $this->dispatch('toast', type: $toast['type'] ?? 'info', message: $toast['message'] ?? '');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Duplicate a job (all fields, title suffixed "(Copy)") and jump straight to
     * the copy's edit page. Counts against the plan's job limit like a new job.
     */
    public function duplicate(int $id)
    {
        $source = Job::forCurrentWorkspace()->findOrFail($id);
        Gate::authorize('create', Job::class);

        if (! app(PlanLimits::class)->canAddJob($this->currentWorkspace())) {
            $this->dispatch('toast', type: 'error', message: "You've reached your plan's job limit. Upgrade to add more.");

            return null;
        }

        $copy = $source->replicate();
        $copy->title = $this->copyTitle($source->title);
        $copy->created_by = Auth::id();
        $copy->save();

        ActivityLogger::log('job.duplicated', $copy, "Duplicated job \"{$source->title}\" as \"{$copy->title}\"");
        session()->flash('flash_toast', ['type' => 'success', 'message' => 'Job duplicated — you are now editing the copy.']);

        return $this->redirectRoute('app.jobs.edit', $copy, navigate: false);
    }

    /** "Title" → "Title (Copy)"; "Title (Copy)" → "Title (Copy 2)", etc. */
    private function copyTitle(string $title): string
    {
        $base = preg_replace('/\s*\(Copy(?: \d+)?\)$/', '', $title) ?? $title;
        $n = 1;
        do {
            $candidate = $base.($n === 1 ? ' (Copy)' : " (Copy {$n})");
            $n++;
        } while (Job::forCurrentWorkspace()->where('title', $candidate)->exists());

        return mb_substr($candidate, 0, 200);
    }

    public function delete(int $id): void
    {
        $job = Job::forCurrentWorkspace()->findOrFail($id);
        Gate::authorize('delete', $job);

        $title = $job->title;
        $job->delete();

        ActivityLogger::log('job.deleted', null, "Deleted job \"{$title}\"");
        $this->dispatch('toast', type: 'info', message: 'Job deleted.');
    }

    public function render()
    {
        $limits = app(PlanLimits::class);
        $workspace = $this->currentWorkspace();

        $jobs = Job::forCurrentWorkspace()
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            // Per-job stats for the list (computed in SQL, no N+1): scored matches,
            // upload batches, and shortlisted / rejected decisions.
            ->withCount([
                'matches as matches_count' => fn ($q) => $q->whereNotNull('score'),
                'batches as batches_count',
                'matches as shortlisted_count' => fn ($q) => $q->where('status', CandidateJobMatch::STATUS_SHORTLISTED),
                'matches as rejected_count' => fn ($q) => $q->where('status', CandidateJobMatch::STATUS_REJECTED),
            ])
            ->latest()
            ->paginate(10);

        return view('livewire.jobs.index', [
            'jobs' => $jobs,
            'jobsUsed' => $limits->jobsUsed($workspace),
            'maxJobs' => $limits->maxJobs($workspace),
            'canManage' => Auth::user()->can('jobs.manage'),
            'canCreate' => Auth::user()->can('jobs.manage') && $limits->canAddJob($workspace),
            'statuses' => Job::statuses(),
        ]);
    }
}
