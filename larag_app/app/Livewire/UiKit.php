<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Component gallery (dev only) — every <x-ui.*> component in one place so the
 * design system can be reviewed in light and dark. Registered only outside
 * production (see routes/web.php).
 */
#[Layout('components.layouts.dashboard')]
#[Title('UI Kit')]
class UiKit extends Component
{
    /** Demo Livewire → toast dispatch. */
    public function notify(string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: ucfirst($type).' toast — a sample message from the server.');
    }

    public function render()
    {
        return view('livewire.ui-kit');
    }
}
