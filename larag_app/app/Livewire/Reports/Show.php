<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use App\Support\CandidateCsvRow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.dashboard')]
#[Title('Screening Report')]
class Show extends Component
{
    use InteractsWithWorkspace;

    public Job $job;

    public function mount(Job $job): void
    {
        abort_unless(
            $job->workspace_id === Auth::user()->workspace_id && Auth::user()->can('reports.view'),
            403
        );

        $this->job = $job;
    }

    public function export()
    {
        abort_unless(Auth::user()->can('reports.view'), 403);

        $rows = CandidateJobMatch::where('job_id', $this->job->id)->whereNotNull('score')
            ->with(['candidate', 'resumeFile'])->orderByDesc('score')->get();
        if ($rows->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Nothing to export — no analyzed candidates for this job yet.');

            return null;
        }

        $filename = 'report-'.Str::slug($this->job->title).'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, CandidateCsvRow::headers());
            foreach ($rows as $m) {
                fputcsv($out, CandidateCsvRow::cells($m));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $bands = config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);
        $base = fn () => CandidateJobMatch::where('job_id', $this->job->id)->whereNotNull('score');

        $counts = [
            ['label' => 'Excellent', 'color' => 'emerald', 'count' => $base()->where('score', '>=', $bands['excellent'])->count()],
            ['label' => 'Good', 'color' => 'indigo', 'count' => $base()->whereBetween('score', [$bands['good'], $bands['excellent'] - 1])->count()],
            ['label' => 'Fair', 'color' => 'amber', 'count' => $base()->whereBetween('score', [$bands['fair'], $bands['good'] - 1])->count()],
            ['label' => 'Low fit', 'color' => 'rose', 'count' => $base()->where('score', '<', $bands['fair'])->count()],
        ];

        // Top missing skills for this job.
        $missing = [];
        foreach ($base()->pluck('missing_skills') as $skills) {
            foreach ((array) $skills as $skill) {
                $skill = trim((string) $skill);
                if ($skill !== '') {
                    $missing[$skill] = ($missing[$skill] ?? 0) + 1;
                }
            }
        }
        arsort($missing);

        return view('livewire.reports.show', [
            'analyzed' => $base()->count(),
            'avg' => (int) round($base()->avg('score') ?? 0),
            'counts' => $counts,
            'topCandidates' => $base()->with('candidate')->orderByDesc('score')->limit(10)->get(),
            'topMissing' => array_slice($missing, 0, 8, true),
        ]);
    }
}
