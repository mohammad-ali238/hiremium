<?php

namespace App\Livewire\Candidates;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\CandidateNote;
use App\Models\ResumeFile;
use App\Services\Activity\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.dashboard')]
#[Title('Candidate')]
class Show extends Component
{
    /** Set when the match's job was deleted by the user: { candidate, job_title, deleted_at }. */
    public ?array $deleted = null;

    use InteractsWithWorkspace;

    public CandidateJobMatch $match;

    public string $newNote = '';

    public string $rejectionReason = '';

    public function mount(CandidateJobMatch $match): void
    {
        // The job (and so this match) was deleted by the user: explain instead of 404ing.
        if ($match->job === null || $match->job->trashed()) {
            $this->deleted = [
                'candidate' => $match->candidate?->name,
                'job_title' => $match->batch?->job_snapshot['title'] ?? $match->job?->title,
                'deleted_at' => $match->job?->deleted_at?->toIso8601String(),
            ];
        }

        abort_unless(
            $match->workspace_id === Auth::user()->workspace_id && Auth::user()->can('candidates.view'),
            403
        );

        $this->match = $match->load(['candidate', 'job', 'resumeFile']);
        $this->rejectionReason = (string) $match->rejection_reason;
    }

    public function changeStatus(string $status): void
    {
        abort_unless(Auth::user()->can('candidates.manage'), 403);
        if (! array_key_exists($status, CandidateJobMatch::statuses())) {
            return;
        }

        $this->match->update([
            'status' => $status,
            'rejection_reason' => $status === CandidateJobMatch::STATUS_REJECTED ? ($this->rejectionReason ?: null) : null,
        ]);

        ActivityLogger::log(
            'candidate.status',
            $this->match->candidate,
            ($this->match->candidate?->name ?? 'Candidate').' → '.CandidateJobMatch::statuses()[$status]
        );
        $this->dispatch('toast', type: 'success', message: 'Status updated.');
    }

    public function addNote(): void
    {
        abort_unless(Auth::user()->can('candidates.manage'), 403);

        $body = trim($this->newNote);
        if ($body === '') {
            return;
        }

        CandidateNote::create([
            'workspace_id' => $this->match->workspace_id,
            'candidate_id' => $this->match->candidate_id,
            'match_id' => $this->match->id,
            'user_id' => Auth::id(),
            'body' => $body,
            'created_at' => now(),
        ]);

        ActivityLogger::log('candidate.note', $this->match->candidate, 'Added a note');
        $this->newNote = '';
        $this->dispatch('toast', type: 'success', message: 'Note added.');
    }

    public function render()
    {
        if ($this->deleted) {
            return view('livewire.candidates.show-deleted', ['deleted' => $this->deleted]);
        }

        // Every CV analyzed for this candidate + job (across batches), newest
        // first. The one backing the match is the "current" best; the rest are
        // history — visible with their own scores, not merged into the analysis.
        $cvHistory = ResumeFile::with('batch')
            ->where('candidate_id', $this->match->candidate_id)
            ->where('job_id', $this->match->job_id)
            ->whereNotNull('score')
            ->orderByDesc('analyzed_at')
            ->orderByDesc('id')
            ->get();

        // The same person's matches on OTHER jobs — so the recruiter can see this
        // profile is one of several and jump between them (each has its own score
        // and reasons). Scoped to the workspace via the candidate.
        $otherMatches = CandidateJobMatch::with('job')
            ->forLiveJobs()
            ->where('candidate_id', $this->match->candidate_id)
            ->where('id', '!=', $this->match->id)
            ->orderByDesc('score')
            ->get();

        return view('livewire.candidates.show', [
            'otherMatches' => $otherMatches,
            'cvHistory' => $cvHistory,
            'notes' => $this->match->candidate?->notes()->with('user')->get() ?? collect(),
            'statuses' => CandidateJobMatch::statuses(),
            'canManage' => Auth::user()->can('candidates.manage'),
        ]);
    }
}
