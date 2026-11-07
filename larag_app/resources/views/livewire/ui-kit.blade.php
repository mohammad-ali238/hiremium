<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <x-ui.breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('app.dashboard')], ['label' => 'UI Kit']]" class="mb-2" />
            <h1 class="text-2xl font-bold text-app-text">UI Kit</h1>
            <p class="mt-1 text-sm text-muted">The shared component system. Toggle dark mode (top bar) to check both themes.</p>
        </div>
    </div>

    {{-- Buttons --}}
    <x-ui.card title="Buttons">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button>Primary</x-ui.button>
            <x-ui.button variant="secondary">Secondary</x-ui.button>
            <x-ui.button variant="subtle">Subtle</x-ui.button>
            <x-ui.button variant="ghost">Ghost</x-ui.button>
            <x-ui.button variant="danger">Danger</x-ui.button>
            <x-ui.button :loading="true">Saving…</x-ui.button>
            <x-ui.button size="sm">Small</x-ui.button>
            <x-ui.button size="lg">Large</x-ui.button>
        </div>
    </x-ui.card>

    {{-- Badges + score pills (signature) --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Badges">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge>Gray</x-ui.badge>
                <x-ui.badge color="primary">Primary</x-ui.badge>
                <x-ui.badge color="success">Hired</x-ui.badge>
                <x-ui.badge color="warning">Reviewing</x-ui.badge>
                <x-ui.badge color="danger">Rejected</x-ui.badge>
            </div>
        </x-ui.card>
        <x-ui.card title="Match score pills" subtitle="The product's signature element">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.score-pill :score="96" />
                <x-ui.score-pill :score="82" />
                <x-ui.score-pill :score="67" />
                <x-ui.score-pill :score="41" />
                <x-ui.score-pill :score="88" :show-label="false" />
            </div>
        </x-ui.card>
    </div>

    {{-- Form controls --}}
    <x-ui.card title="Form controls">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.field label="Full name" for="k-name" hint="As it appears on the CV.">
                <x-ui.input id="k-name" placeholder="Ayesha Khan" />
            </x-ui.field>
            <x-ui.field label="Email" for="k-email" error="Enter a valid email address." required>
                <x-ui.input id="k-email" type="email" :error="true" value="not-an-email" />
            </x-ui.field>
            <x-ui.field label="Job" for="k-job">
                <x-ui.select id="k-job">
                    <option>Senior Laravel Developer</option>
                    <option>Frontend Engineer</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Notes" for="k-notes">
                <x-ui.textarea id="k-notes" placeholder="Add a note…" />
            </x-ui.field>
            <div class="flex items-center gap-6">
                <x-ui.switch label="Anonymized screening" />
                <x-ui.checkbox label="Only shortlisted" />
            </div>
        </div>
    </x-ui.card>

    {{-- Avatars, tooltip, dropdown, tabs --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Avatars, tooltip &amp; dropdown">
            <div class="flex flex-wrap items-center gap-4">
                <x-ui.avatar name="Ayesha Khan" size="lg" />
                <x-ui.avatar name="Ali Raza" />
                <x-ui.avatar name="John" size="sm" />
                <x-ui.tooltip text="Highly recommended">
                    <x-ui.badge color="success">Hover me</x-ui.badge>
                </x-ui.tooltip>
                <x-ui.dropdown align="left">
                    <x-slot:trigger>
                        <x-ui.button variant="secondary" size="sm">Actions ▾</x-ui.button>
                    </x-slot:trigger>
                    <x-ui.dropdown-item>Shortlist</x-ui.dropdown-item>
                    <x-ui.dropdown-item>Compare</x-ui.dropdown-item>
                    <x-ui.dropdown-item :danger="true">Reject</x-ui.dropdown-item>
                </x-ui.dropdown>
            </div>
        </x-ui.card>
        <x-ui.card title="Tabs">
            <x-ui.tabs :tabs="['overview' => 'Overview', 'skills' => 'Skills', 'cv' => 'CV']">
                <div x-show="tab === 'overview'" class="text-sm text-muted">Parsed summary, experience and education would render here.</div>
                <div x-show="tab === 'skills'" x-cloak class="text-sm text-muted">Matched and missing skills would render here.</div>
                <div x-show="tab === 'cv'" x-cloak class="text-sm text-muted">The original CV preview would render here.</div>
            </x-ui.tabs>
        </x-ui.card>
    </div>

    {{-- Table --}}
    <x-ui.card title="Data table" :padding="false">
        <x-ui.table class="rounded-none border-0">
            <thead>
                <tr>
                    <x-ui.th>Candidate</x-ui.th>
                    <x-ui.th>Match</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th>Recommendation</x-ui.th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ([['Ayesha Khan', 96, 'Shortlisted', 'success'], ['Ali Raza', 82, 'New', 'primary'], ['John Smith', 67, 'Reviewing', 'warning']] as [$n, $sc, $st, $col])
                    <tr class="hover:bg-primary-soft/30">
                        <x-ui.td>
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$n" size="sm" />
                                <span class="font-medium">{{ $n }}</span>
                            </div>
                        </x-ui.td>
                        <x-ui.td><x-ui.score-pill :score="$sc" /></x-ui.td>
                        <x-ui.td><x-ui.badge :color="$col">{{ $st }}</x-ui.badge></x-ui.td>
                        <x-ui.td class="text-muted">{{ $sc >= 90 ? 'Highly recommended' : ($sc >= 75 ? 'Recommended' : 'Consider') }}</x-ui.td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>

    {{-- Progress + stepper --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Progress">
            <div class="space-y-4">
                <x-ui.progress :value="64" label="AI usage" :show-value="true" />
                <x-ui.progress :value="90" color="success" label="Batch complete" :show-value="true" />
                <x-ui.progress :value="20" color="warning" label="Parsing" :show-value="true" />
            </div>
        </x-ui.card>
        <x-ui.card title="Batch stepper">
            <x-ui.stepper :stages="[
                ['label' => 'Uploaded', 'count' => 50, 'state' => 'done'],
                ['label' => 'Parsed', 'count' => 32, 'state' => 'active'],
                ['label' => 'Analyzed', 'count' => 0, 'state' => 'pending'],
                ['label' => 'Failed', 'count' => 1, 'state' => 'failed'],
            ]" />
        </x-ui.card>
    </div>

    {{-- Upload --}}
    <x-ui.card title="Upload box">
        <x-ui.upload />
    </x-ui.card>

    {{-- Loaders + skeleton + empty --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Loaders">
            <div class="flex items-center gap-4">
                <x-ui.loader size="sm" /><x-ui.loader /><x-ui.loader size="lg" />
            </div>
        </x-ui.card>
        <x-ui.card title="Skeleton">
            <div class="space-y-2">
                <x-ui.skeleton class="h-4 w-3/4" />
                <x-ui.skeleton class="h-4 w-1/2" />
                <x-ui.skeleton class="h-24 w-full" />
            </div>
        </x-ui.card>
        <x-ui.card title="Empty state" :padding="false">
            <x-ui.empty title="No candidates yet" description="Upload resumes to a job to see AI-scored candidates here.">
                <x-slot:action><x-ui.button size="sm">Upload resumes</x-ui.button></x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    </div>

    {{-- Overlays: modal, confirm, toast --}}
    <x-ui.card title="Modal, confirm &amp; toasts">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button variant="secondary" x-on:click="$dispatch('open-modal', 'kit-demo')">Open modal</x-ui.button>
            <x-ui.button variant="danger"
                         x-on:click="$store.confirm.ask({ title: 'Reject candidate?', text: 'They will be moved to the rejected list.', danger: true, confirmText: 'Reject' }).then(ok => ok && window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Candidate rejected.' } })))">
                Confirm dialog
            </x-ui.button>
            <x-ui.button variant="secondary" wire:click="notify('success')">Success toast</x-ui.button>
            <x-ui.button variant="secondary" wire:click="notify('error')">Error toast</x-ui.button>
        </div>

        <x-ui.modal name="kit-demo" title="Candidate details">
            <p class="text-sm text-muted">This is a modal built from the design tokens. It opens on a name-matched event and closes on escape, backdrop click, or the ✕.</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'kit-demo')">Cancel</x-ui.button>
                <x-ui.button x-on:click="$dispatch('close-modal', 'kit-demo')">Got it</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    </x-ui.card>
</div>
