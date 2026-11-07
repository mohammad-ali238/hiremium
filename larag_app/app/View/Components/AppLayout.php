<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  array<string, string>|null  $seo  Per-record meta for pages whose
     *                                           SEO is not keyed by route name (blog articles and archives).
     */
    public function __construct(
        public ?string $title = null,
        public ?array $seo = null,
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
