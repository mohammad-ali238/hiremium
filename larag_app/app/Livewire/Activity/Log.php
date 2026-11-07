<?php

namespace App\Livewire\Activity;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.dashboard')]
#[Title('Activity Log')]
class Log extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('activity.view'), 403);
    }

    public function render()
    {
        $activities = Activity::with('user')
            ->where('workspace_id', Auth::user()->workspace_id)
            ->latest('created_at')->latest('id')
            ->paginate(20);

        return view('livewire.activity.log', ['activities' => $activities]);
    }
}
