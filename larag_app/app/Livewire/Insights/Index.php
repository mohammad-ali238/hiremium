<?php

namespace App\Livewire\Insights;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.dashboard')]
#[Title('Screening Insights')]
class Index extends Component
{
    use InteractsWithWorkspace;

    #[Url]
    public string $jobFilter = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('reports.view'), 403);
    }

    public function render()
    {
        $workspaceId = $this->currentWorkspace()->id;
        $bands = config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);

        $base = fn () => CandidateJobMatch::where('workspace_id', $workspaceId)->forLiveJobs()->whereNotNull('score')
            ->when($this->jobFilter !== '', fn ($q) => $q->where('job_id', $this->jobFilter));

        $analyzed = $base()->count();

        $distribution = [
            ['label' => 'Excellent', 'range' => '90–100', 'color' => 'emerald', 'count' => $base()->where('score', '>=', $bands['excellent'])->count()],
            ['label' => 'Good', 'range' => '75–89', 'color' => 'indigo', 'count' => $base()->whereBetween('score', [$bands['good'], $bands['excellent'] - 1])->count()],
            ['label' => 'Fair', 'range' => '60–74', 'color' => 'amber', 'count' => $base()->whereBetween('score', [$bands['fair'], $bands['good'] - 1])->count()],
            ['label' => 'Low fit', 'range' => '<60', 'color' => 'rose', 'count' => $base()->where('score', '<', $bands['fair'])->count()],
        ];

        $perJob = Job::where('workspace_id', $workspaceId)->latest()->limit(10)->get()->map(function (Job $job) {
            $q = CandidateJobMatch::where('job_id', $job->id)->whereNotNull('score');

            return [
                'job' => $job,
                'analyzed' => (clone $q)->count(),
                'avg' => (int) round((clone $q)->avg('score') ?? 0),
                'top' => (clone $q)->max('score'),
            ];
        });

        return view('livewire.insights.index', [
            'analyzed' => $analyzed,
            'avgScore' => (int) round($base()->avg('score') ?? 0),
            'distribution' => $distribution,
            'topMissing' => $this->topMissing($workspaceId),
            'perJob' => $perJob,
            'jobs' => Job::where('workspace_id', $workspaceId)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    /** @return array<array{skill:string,count:int}> */
    private function topMissing(int $workspaceId): array
    {
        $counts = [];
        $q = CandidateJobMatch::where('workspace_id', $workspaceId)->forLiveJobs()->whereNotNull('missing_skills')
            ->when($this->jobFilter !== '', fn ($qq) => $qq->where('job_id', $this->jobFilter));

        foreach ($q->pluck('missing_skills') as $skills) {
            foreach ((array) $skills as $skill) {
                $skill = trim((string) $skill);
                if ($skill !== '') {
                    $counts[$skill] = ($counts[$skill] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return array_map(fn ($skill, $count) => ['skill' => $skill, 'count' => $count],
            array_slice(array_keys($counts), 0, 8),
            array_slice(array_values($counts), 0, 8));
    }
}
