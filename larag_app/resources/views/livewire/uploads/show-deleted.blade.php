<div class="space-y-6">
    <x-ui.breadcrumbs :items="[['label' => 'Resume Upload', 'url' => route('app.upload')], ['label' => 'Batch #'.$deleted['id']]]" class="mb-2" />
    <x-ui.deleted-record
        title="This batch was deleted"
        description="The upload batch you followed a link to has been removed from your workspace — most likely because the job it belonged to was deleted. Its candidates and results are no longer shown in the dashboard."
        :label="'Batch #'.$deleted['id'].($deleted['job_title'] ? ' — '.$deleted['job_title'] : '')"
        :deleted-at="$deleted['deleted_at']">
        <x-ui.button href="{{ route('app.upload') }}" wire:navigate>Resume Upload</x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('app.jobs') }}" wire:navigate>Your jobs</x-ui.button>
    </x-ui.deleted-record>
</div>
