<div class="space-y-6">
    <x-ui.breadcrumbs :items="[['label' => 'Matched Candidates', 'url' => route('app.candidates')], ['label' => $deleted['candidate'] ?? 'Candidate']]" class="mb-2" />
    <x-ui.deleted-record
        title="This match belongs to a deleted job"
        description="The job this candidate was analyzed against has been deleted, so the match and its score are no longer part of your dashboard."
        :label="($deleted['candidate'] ?? 'Candidate').($deleted['job_title'] ? ' → '.$deleted['job_title'] : '')"
        :deleted-at="$deleted['deleted_at']">
        <x-ui.button href="{{ route('app.candidates') }}" wire:navigate>Matched candidates</x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('app.jobs') }}" wire:navigate>Your jobs</x-ui.button>
    </x-ui.deleted-record>
</div>
