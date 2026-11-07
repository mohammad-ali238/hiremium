<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.dashboard')]
#[Title('Screening Reports')]
class Index extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('reports.view'), 403);
    }

    public function render()
    {
        $jobs = Job::forCurrentWorkspace()->latest()->paginate(15);

        $jobs->getCollection()->transform(function (Job $job) {
            $q = CandidateJobMatch::where('job_id', $job->id)->whereNotNull('score');
            $job->setAttribute('analyzed_count', (clone $q)->count());
            $job->setAttribute('top_score', (clone $q)->max('score'));

            return $job;
        });

        return view('livewire.reports.index', ['jobs' => $jobs]);
    }
}
