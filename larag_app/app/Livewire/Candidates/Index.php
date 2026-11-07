<?php

namespace App\Livewire\Candidates;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Candidate;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use App\Services\Activity\ActivityLogger;
use App\Support\CandidateCsvRow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.dashboard')]
#[Title('Matched Candidates')]
class Index extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $jobFilter = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $band = '';

    #[Url]
    public string $sort = 'score';

    public array $selected = [];

    public bool $shortlistView = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('candidates.view'), 403);

        if (request()->routeIs('app.shortlists')) {
            $this->shortlistView = true;
            $this->status = CandidateJobMatch::STATUS_SHORTLISTED;
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'jobFilter', 'status', 'band', 'sort'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    protected function baseQuery()
    {
        $bands = config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);

        $query = CandidateJobMatch::forCurrentWorkspace()
            ->forLiveJobs()
            ->with(['candidate', 'job', 'batch'])
            ->whereNotNull('score')
            ->when($this->jobFilter !== '', fn ($q) => $q->where('job_id', $this->jobFilter))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->band === 'excellent', fn ($q) => $q->where('score', '>=', $bands['excellent']))
            ->when($this->band === 'good', fn ($q) => $q->whereBetween('score', [$bands['good'], $bands['excellent'] - 1]))
            ->when($this->band === 'fair', fn ($q) => $q->whereBetween('score', [$bands['fair'], $bands['good'] - 1]))
            ->when($this->band === 'low', fn ($q) => $q->where('score', '<', $bands['fair']))
            ->when($this->search !== '', fn ($q) => $q->whereHas('candidate', fn ($c) => $c
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')));

        return match ($this->sort) {
            'name' => $query->orderBy(Candidate::select('name')->whereColumn('candidates.id', 'candidate_job_matches.candidate_id')),
            'date' => $query->latest('id'),
            default => $query->orderByDesc('score')->orderByDesc('id'),
        };
    }

    /** Job snapshot shown in the "Job details" popup (from the match's batch). */
    public ?array $jobSnapshot = null;

    public function showJob(int $matchId): void
    {
        $match = CandidateJobMatch::forCurrentWorkspace()->with(['batch', 'job'])->findOrFail($matchId);

        $this->jobSnapshot = $match->batch?->job_snapshot ?: $match->job?->toSnapshot();

        $this->dispatch('open-modal', 'job-snapshot');
    }

    public function bulkStatus(string $status): void
    {
        abort_unless(Auth::user()->can('candidates.manage'), 403);
        if (! array_key_exists($status, CandidateJobMatch::statuses()) || empty($this->selected)) {
            return;
        }

        $count = CandidateJobMatch::forCurrentWorkspace()
            ->whereIn('id', $this->selected)
            ->update(['status' => $status]);

        ActivityLogger::log('candidate.bulk_status', null, "Set {$count} candidate(s) to ".CandidateJobMatch::statuses()[$status]);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: "{$count} candidate(s) updated.");
    }

    public function compare()
    {
        abort_unless(Auth::user()->can('candidates.view'), 403);

        $ids = array_slice($this->selected, 0, 3);
        if (count($ids) < 2) {
            $this->dispatch('toast', type: 'error', message: 'Select 2 or 3 candidates to compare.');

            return null;
        }

        return $this->redirectRoute('app.compare', ['ids' => implode(',', $ids)], navigate: false);
    }

    public function export()
    {
        abort_unless(Auth::user()->can('candidates.view'), 403);

        $rows = $this->baseQuery()->with('resumeFile')->get();
        if ($rows->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Nothing to export — no candidates match the current filters.');

            return null;
        }
        $filename = 'candidates-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, CandidateCsvRow::headers(withJob: true));
            foreach ($rows as $m) {
                fputcsv($out, CandidateCsvRow::cells($m, withJob: true));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.candidates.index', [
            'matches' => $this->baseQuery()->paginate(15),
            'jobs' => Job::forCurrentWorkspace()->orderBy('title')->get(['id', 'title']),
            'statuses' => CandidateJobMatch::statuses(),
            'canManage' => Auth::user()->can('candidates.manage'),
        ]);
    }
}
