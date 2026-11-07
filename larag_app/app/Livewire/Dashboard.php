<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Activity;
use App\Models\Candidate;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use App\Services\Billing\WorkspaceCredits;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Recruiter workspace home — stat cards, best candidates, recent jobs, the AI
 * screening summary, credit usage and the recent-activity feed.
 */
#[Layout('components.layouts.dashboard')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use InteractsWithWorkspace;

    public function render()
    {
        $workspace = $this->currentWorkspace();
        $workspaceId = $workspace->id;
        $bands = config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);

        $scored = fn () => CandidateJobMatch::where('workspace_id', $workspaceId)->forLiveJobs()->whereNotNull('score');

        $credits = app(WorkspaceCredits::class)->balance($workspace);
        // Total credits come from the owner's own subscription snapshot.
        $monthlyTotal = (int) ($workspace->ownerSubscription()?->creditsTotal() ?? 0);

        // Recent jobs with their candidate count + top score.
        $recentJobs = Job::where('workspace_id', $workspaceId)->latest()->limit(5)->get()
            ->map(function (Job $job) {
                $q = CandidateJobMatch::where('job_id', $job->id)->whereNotNull('score');

                return ['job' => $job, 'candidates' => (clone $q)->count(), 'top' => (clone $q)->max('score')];
            });

        return view('livewire.dashboard', [
            'stats' => [
                'candidates' => Candidate::where('workspace_id', $workspaceId)->count(),
                'jobs' => Job::where('workspace_id', $workspaceId)->count(),
                'topMatches' => $scored()->where('score', '>=', $bands['excellent'])->count(),
                'creditsTotal' => $credits['total'],
            ],
            'creditsMonthly' => $credits['monthly'],
            'monthlyTotal' => $monthlyTotal,
            'bestCandidates' => $scored()->with(['candidate', 'job'])->orderByDesc('score')->limit(5)->get(),
            'recentJobs' => $recentJobs,
            'screening' => [
                'analyzed' => $scored()->count(),
                'excellent' => $scored()->where('score', '>=', $bands['excellent'])->count(),
                'good' => $scored()->whereBetween('score', [$bands['good'], $bands['excellent'] - 1])->count(),
                'low' => $scored()->where('score', '<', $bands['fair'])->count(),
            ],
            'topMissing' => $this->topMissingSkills($workspaceId),
            'recent' => Activity::with('user')->where('workspace_id', $workspaceId)->latest('created_at')->latest('id')->limit(5)->get(),
            'canView' => Auth::user()->can('candidates.view'),
        ]);
    }

    /** Aggregate the most common missing skills across scored candidates. */
    private function topMissingSkills(int $workspaceId): array
    {
        $counts = [];

        foreach (CandidateJobMatch::where('workspace_id', $workspaceId)->forLiveJobs()->whereNotNull('missing_skills')->pluck('missing_skills') as $skills) {
            foreach ((array) $skills as $skill) {
                $skill = trim((string) $skill);
                if ($skill !== '') {
                    $counts[$skill] = ($counts[$skill] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, 5);
    }
}
