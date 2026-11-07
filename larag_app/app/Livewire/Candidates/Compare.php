<?php

namespace App\Livewire\Candidates;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.dashboard')]
#[Title('Compare candidates')]
class Compare extends Component
{
    use InteractsWithWorkspace;

    #[Url]
    public string $ids = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('candidates.view'), 403);
    }

    public function remove(int $id): void
    {
        $kept = array_values(array_filter($this->idList(), fn ($x) => $x !== $id));
        $this->ids = implode(',', $kept);
    }

    /** @return int[] up to 3 match ids, in order. */
    private function idList(): array
    {
        return array_slice(array_values(array_filter(array_map('intval', explode(',', $this->ids)))), 0, 3);
    }

    public function render()
    {
        $idList = $this->idList();

        $found = $idList
            ? CandidateJobMatch::forCurrentWorkspace()->forLiveJobs()->with(['candidate', 'job', 'resumeFile'])->whereIn('id', $idList)->get()
            : collect();

        // Preserve the requested order.
        $matches = collect($idList)->map(fn ($id) => $found->firstWhere('id', $id))->filter()->values();

        return view('livewire.candidates.compare', ['matches' => $matches]);
    }
}
