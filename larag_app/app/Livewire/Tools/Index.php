<?php

namespace App\Livewire\Tools;

use App\Services\Tools\ToolRegistry;
use App\Support\ToolSettings;
use Livewire\Attributes\Title;
use Livewire\Component;

/** /tools — the catalogue of enabled free tools. */
#[Title('Free Tools')]
class Index extends Component
{
    public function render(ToolRegistry $registry)
    {
        return view('livewire.tools.index', [
            'tools' => $registry->enabled(),
        ])->layout('components.layouts.tool-page', [
            'seo' => ToolSettings::seoFor('index', [
                'title' => 'Free AI hiring tools — try Hiremium without an account',
                'description' => 'Free tools that run the same AI Hiremium uses for bulk resume screening. No account needed — confirm your email and the result is yours to keep and share.',
                'keywords' => 'free resume tools, ai resume checker, free cv match, hiring tools',
            ]),
        ]);
    }
}
