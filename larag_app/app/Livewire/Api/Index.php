<?php

namespace App\Livewire\Api;

use App\Livewire\Concerns\InteractsWithWorkspace;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * API access — key generation + usage log. Owner-only (the account holder).
 */
#[Layout('components.layouts.dashboard')]
#[Title('API')]
class Index extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    /** Shown once, right after generation. */
    public ?string $plainTextToken = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isOwnerOfCurrentWorkspace(), 403);
    }

    public function generate(): void
    {
        abort_unless(Auth::user()->isOwnerOfCurrentWorkspace(), 403);

        $user = Auth::user();
        $user->tokens()->delete(); // single-key policy: replace any existing key
        $this->plainTextToken = $user->createToken('api-key')->plainTextToken;

        $this->dispatch('toast', type: 'success', message: "API key generated — copy it now, it won't be shown again.");
    }

    public function revoke(): void
    {
        abort_unless(Auth::user()->isOwnerOfCurrentWorkspace(), 403);

        Auth::user()->tokens()->delete();
        $this->plainTextToken = null;

        $this->dispatch('toast', type: 'info', message: 'API key revoked.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.api.index', [
            'token' => $user->tokens()->latest('id')->first(),
            'logs' => $user->usageLogs()->latest('id')->paginate(15),
        ]);
    }
}
