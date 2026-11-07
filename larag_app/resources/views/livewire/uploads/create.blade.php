<div x-data="{ tab: 'files' }" :class="tab === 'csv' ? 'max-w-5xl' : 'max-w-3xl'" class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-2xl font-bold text-app-text">Resume Upload</h1>
        <x-ui.badge :color="$credits < $creditCost ? 'danger' : ($credits <= 10 ? 'warning' : 'success')">
            {{ number_format($credits) }} AI request(s)
        </x-ui.badge>
    </div>

    {{-- Upload mode tabs --}}
    <div class="flex gap-1 border-b border-line" role="tablist">
        <button type="button" role="tab" @click="tab = 'files'" :aria-selected="tab === 'files'"
                :class="tab === 'files' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-app-text'"
                class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Bulk Files Upload
        </button>
        <button type="button" role="tab" @click="tab = 'csv'" :aria-selected="tab === 'csv'"
                :class="tab === 'csv' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-app-text'"
                class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition">
            CSV Upload
        </button>
    </div>

    {{-- TAB 2: CSV import with AI column mapping --}}
    <div x-show="tab === 'csv'" x-cloak>
        <livewire:uploads.csv-mapper wire:key="csv-mapper" />
    </div>

    <div x-show="tab === 'files'" class="space-y-6">
    {{-- Upload rules, prominent --}}
    <div class="flex items-start gap-3 rounded-lg border border-primary/30 bg-primary-soft/40 px-4 py-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
        <div class="text-sm text-app-text">
            <p>Upload up to <span class="font-semibold">{{ $maxFiles }} CVs</span> at once — or a ZIP with all CV files at once, which is auto-split into batches of {{ $maxFiles }}.</p>
            <p class="mt-0.5 text-muted"><span class="font-medium text-app-text">{{ $maxFileMb }} MB</span> per file · PDF, DOCX, DOC, TXT.</p>
        </div>
    </div>

    @if ($credits < $creditCost)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-danger/30 bg-danger/5 px-4 py-3">
            <p class="text-sm text-app-text">You have no AI usage left to analyze CVs. Uploading is paused until you top up.</p>
            <x-ui.button href="{{ route('app.billing') }}" size="sm">Top up AI usage</x-ui.button>
        </div>
    @endif

    @if ($jobs->isEmpty())
        <x-ui.card>
            <x-ui.empty title="Create a job first" description="Resumes are scored against a job, so add one before uploading.">
                <x-slot:action><x-ui.button href="{{ route('app.jobs.create') }}">New job</x-ui.button></x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card>
            <form wire:submit="save" class="space-y-5">
                <x-ui.field label="Assign to job" for="jobId" required :error="$errors->first('jobId')">
                    <x-ui.select id="jobId" wire:model.live="jobId">
                        <option value="">Select a job…</option>
                        @foreach ($jobs as $job)
                            <option value="{{ $job->id }}">{{ $job->title }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div x-data="{
                        over: false, progress: 0, uploading: false,
                        max: {{ (int) $maxFiles }},
                        maxBytes: {{ (int) $maxFileMb * 1024 * 1024 }},
                        maxMb: {{ (int) $maxFileMb }},
                        allowed: ['pdf','doc','docx','txt','zip'],
                        // Read the committed count live from the rendered list (Livewire morphs the
                        // DOM on each change), so successive picks always see the current total.
                        get ready() { return document.querySelectorAll('[data-ready-file]').length; },
                        // Client-side pre-check: BEFORE Livewire uploads anything, drop files that are
                        // too large / wrong type / beyond the plan cap. Their names + reasons are sent
                        // to the server (no bytes), so the batch page can list them. Capture phase so
                        // this runs ahead of Livewire's own change handler.
                        guard(e) {
                            const input = e.target;
                            const picked = Array.from(input.files);
                            if (! picked.length) return;
                            const keep = [], refused = [];
                            let room = Math.max(0, this.max - this.ready);
                            for (const f of picked) {
                                const ext = (f.name.split('.').pop() || '').toLowerCase();
                                const ceiling = ext === 'zip' ? this.maxBytes * 3 : this.maxBytes;
                                if (! this.allowed.includes(ext)) { refused.push({ name: f.name, reason: 'File type not allowed — use PDF, DOCX, DOC or TXT' }); continue; }
                                if (f.size > ceiling) { refused.push({ name: f.name, reason: `File too large (${(f.size/1048576).toFixed(1)} MB) — limit is ${ext === 'zip' ? this.maxMb * 3 : this.maxMb} MB per file` }); continue; }
                                if (room <= 0) { refused.push({ name: f.name, reason: `Over the plan limit of ${this.max} CVs per upload` }); continue; }
                                keep.push(f); room--;
                            }
                            if (! refused.length) return;
                            const dt = new DataTransfer(); keep.forEach(f => dt.items.add(f)); input.files = dt.files;
                            $wire.call('rejectClientSide', refused);
                            const overCap = refused.some(r => r.reason.startsWith('Over the plan limit'));
                            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning',
                                message: `${refused.length} file${refused.length === 1 ? ' was' : 's were'} not added (see \u201cSkipped files\u201d after upload for the reasons).${overCap ? ` Your plan allows ${this.max} CVs per upload — upgrade to upload more at once.` : ''}` } }));
                            if (! keep.length) { e.stopImmediatePropagation(); input.value = ''; }
                        },
                        dropFiles(e) {
                            const files = e.dataTransfer?.files;
                            if (! files || ! files.length) return;
                            const input = this.$el.querySelector('input[type=file]');
                            const dt = new DataTransfer();
                            Array.from(files).forEach(f => dt.items.add(f));
                            input.files = dt.files;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                     }"
                     x-init="$el.querySelector('input[type=file]').addEventListener('change', guard.bind($data), true)"
                     x-on:livewire-upload-start="uploading = true"
                     x-on:livewire-upload-finish="uploading = false; progress = 0"
                     x-on:livewire-upload-error="uploading = false"
                     x-on:livewire-upload-progress="progress = $event.detail.progress">
                    {{-- Drop handler: hand the dropped files to the file input and fire `change`, so a
                         drop takes the exact same path as a click (guard → Livewire upload). --}}
                    <div @dragover.prevent="over = true" @dragleave.prevent="over = false"
                         @drop.prevent="over = false; dropFiles($event)"
                         :class="over ? 'border-primary bg-primary-soft/40' : 'border-line'"
                         class="relative rounded-xl border-2 border-dashed bg-surface p-8 text-center transition">
                        <input type="file" wire:model="incoming" multiple accept=".pdf,.docx,.doc,.txt,.zip"
                               class="absolute inset-0 cursor-pointer opacity-0" aria-label="Upload CVs">
                        <div class="pointer-events-none">
                            <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 9 5-5 5 5"/><path d="M12 4v12"/></svg>
                            </span>
                            <p class="text-sm font-medium text-app-text">Drag &amp; drop CVs here, or <span class="text-primary">browse</span></p>
                            <p class="mt-1 text-xs text-muted">Up to {{ $maxFiles }} files — or one ZIP, auto-split into batches of {{ $maxFiles }}</p>
                        </div>
                    </div>

                    <div x-show="uploading" x-cloak class="mt-3">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-line/70">
                            <div class="h-full rounded-full bg-primary transition-all" :style="`width: ${progress}%`"></div>
                        </div>
                        <p class="mt-1 text-xs text-muted">Uploading… <span x-text="progress"></span>%</p>
                    </div>
                </div>

                @error('files') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                @error('files.*') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                @error('incoming.*') <p class="text-xs text-danger">{{ $message }}</p> @enderror

                @if (! empty($files))
                    <div class="rounded-lg border border-line divide-y divide-line">
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs font-medium text-muted">
                            <span>
                                <span class="{{ count($files) >= $maxFiles ? 'text-warning' : 'text-app-text' }}">{{ count($files) }} of {{ $maxFiles }}</span>
                                file(s) ready
                            </span>
                            @if (count($files) >= $maxFiles)
                                <span class="text-warning">
                                    Plan limit reached ({{ $maxFiles }} per upload) —
                                    <a href="{{ route('app.usage') }}" class="font-semibold underline hover:text-app-text" wire:navigate>upgrade for more</a>
                                </span>
                            @endif
                        </div>
                        {{-- Rows: show up to 10, then the list scrolls (header above stays put). --}}
                        <div class="divide-y divide-line {{ count($files) > 10 ? 'max-h-[26rem] overflow-y-auto' : '' }}">
                        @foreach ($files as $i => $file)
                            <div class="flex items-center justify-between gap-3 px-4 py-2.5" wire:key="file-{{ $i }}" data-ready-file>
                                <span class="truncate text-sm text-app-text">{{ $file->getClientOriginalName() }}</span>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="text-xs text-muted">{{ number_format($file->getSize() / 1024, 0) }} KB</span>
                                    <button type="button" wire:click="removeFile({{ $i }})"
                                            class="text-muted hover:text-danger" aria-label="Remove file">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2">
                    {{-- Disabled while files are still uploading, so a submit never sends a partial selection. --}}
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save,incoming">
                        <span wire:loading.remove wire:target="save,incoming">Upload &amp; Analyze</span>
                        <span wire:loading wire:target="incoming">Uploading files…</span>
                        <span wire:loading wire:target="save">Storing…</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- Recent uploads — filtered to the selected job (all jobs when none chosen); 10 per page. --}}
        <x-ui.card :title="$jobId ? 'Recent uploads for this job' : 'Recent uploads'" :padding="false">
            @if ($recent->isEmpty())
                <x-ui.empty title="No uploads yet" :description="$jobId ? 'No resumes have been uploaded to this job yet.' : 'Your uploads will appear here.'" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recent as $b)
                        <li class="flex items-center justify-between gap-3 px-5 py-3" wire:key="recent-{{ $b->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('app.batches.show', $b) }}" class="text-sm font-medium text-app-text hover:text-primary">{{ $b->job?->title ?? 'Job' }}</a>
                                <p class="text-xs text-muted">Batch #{{ $b->id }} · {{ $b->total_files }} file(s) · {{ $b->created_at?->diffForHumans() }}</p>
                            </div>
                            <x-ui.batch-status :status="$b->status" />
                        </li>
                    @endforeach
                </ul>
                @if ($recent->hasPages())
                    <div class="border-t border-line px-5 py-3">
                        {{ $recent->links() }}
                    </div>
                @endif
            @endif
        </x-ui.card>
    @endif
    </div>
</div>
