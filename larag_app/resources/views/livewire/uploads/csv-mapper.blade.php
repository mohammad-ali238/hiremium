<div class="space-y-6">
    {{-- CSV tab rules, mirrors the files tab's info box --}}
    <div class="flex items-start gap-3 rounded-lg border border-primary/30 bg-primary-soft/40 px-4 py-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
        <div class="text-sm text-app-text">
            <p>Upload <span class="font-semibold">one CSV file</span> of candidates — we'll match its columns to candidate fields for you to review.</p>
            <p class="mt-0.5 text-muted"><span class="font-medium text-app-text">{{ $maxFileMb }} MB</span> max · the first row should contain column headings.</p>
        </div>
    </div>

    <div class="{{ $stage === 'mapped' ? 'grid items-start gap-6 lg:grid-cols-5' : '' }}">
        {{-- LEFT: job + file picker --}}
        <x-ui.card class="{{ $stage === 'mapped' ? 'lg:col-span-2' : '' }}">
            <div class="space-y-5">
                <x-ui.field label="Assign to job" for="csvJobId" required :error="$errors->first('jobId')">
                    <x-ui.select id="csvJobId" wire:model="jobId">
                        <option value="">Select a job…</option>
                        @foreach ($jobs as $job)
                            <option value="{{ $job->id }}">{{ $job->title }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                @if ($error)
                    <div class="flex items-start gap-3 rounded-lg border border-danger/30 bg-danger/5 px-4 py-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                        <p class="text-sm text-app-text">{{ $error }}</p>
                    </div>
                @endif

                @if ($stage === 'mapped')
                    {{-- Picked-file summary --}}
                    <div wire:key="csv-picked" class="flex items-center justify-between gap-3 rounded-lg border border-line px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-app-text">{{ $fileName }}</p>
                            <p class="text-xs text-muted">{{ number_format($rowCount) }} candidate row(s) · {{ count($headers) }} columns</p>
                        </div>
                        <button type="button" wire:click="removeCsv" class="shrink-0 text-xs font-medium text-muted underline hover:text-danger">Change file</button>
                    </div>
                @else
                    {{-- wire:key forces a REPLACE (not an in-place patch) when this branch
                         swaps with the summary above, so Alpine re-initialises x-data. --}}
                    <div wire:key="csv-dropzone" x-data="{
                            over: false,
                            maxBytes: {{ (int) $maxFileMb * 1024 * 1024 }},
                            // Pre-check before Livewire uploads anything: single .csv within the size cap.
                            guard(e) {
                                const input = e.target;
                                const f = input.files?.[0];
                                if (! f) return;
                                let reason = null;
                                if (! /\.csv$/i.test(f.name)) reason = 'Only CSV files are accepted here — save your spreadsheet as .csv and try again.';
                                else if (f.size > this.maxBytes) reason = `File too large (${(f.size/1048576).toFixed(1)} MB) — limit is {{ (int) $maxFileMb }} MB.`;
                                if (reason) {
                                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: reason } }));
                                    e.stopImmediatePropagation(); input.value = '';
                                }
                            },
                            dropFiles(e) {
                                const files = e.dataTransfer?.files;
                                if (! files || ! files.length) return;
                                const input = this.$el.querySelector('input[type=file]');
                                const dt = new DataTransfer(); dt.items.add(files[0]);
                                input.files = dt.files;
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                         }"
                         x-init="$el.querySelector('input[type=file]').addEventListener('change', guard.bind($data), true)">
                        <div @dragover.prevent="over = true" @dragleave.prevent="over = false"
                             @drop.prevent="over = false; dropFiles($event)"
                             :class="over ? 'border-primary bg-primary-soft/40' : 'border-line'"
                             class="relative rounded-xl border-2 border-dashed bg-surface p-8 text-center transition">
                            <input type="file" wire:model="csvFile" accept=".csv"
                                   class="absolute inset-0 cursor-pointer opacity-0" aria-label="Upload candidate CSV">
                            <div class="pointer-events-none" wire:loading.remove wire:target="csvFile">
                                <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h8"/></svg>
                                </span>
                                <p class="text-sm font-medium text-app-text">Drag &amp; drop your CSV here, or <span class="text-primary">browse</span></p>
                                <p class="mt-1 text-xs text-muted">One .csv file · up to {{ $maxFileMb }} MB</p>
                            </div>
                            {{-- Loader: covers the upload AND the sampling + column-matching request --}}
                            <div class="pointer-events-none" wire:loading.flex wire:target="csvFile" style="display:none">
                                <div class="mx-auto flex flex-col items-center gap-3">
                                    <svg class="h-8 w-8 animate-spin text-primary" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                                    <p class="text-sm font-medium text-app-text">Reading your CSV and matching columns…</p>
                                    <p class="text-xs text-muted">This usually takes a few seconds.</p>
                                </div>
                            </div>
                        </div>
                        @error('csvFile') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </x-ui.card>

        {{-- RIGHT (below on small screens): mapping confirmation --}}
        @if ($stage === 'mapped')
            <x-ui.card class="lg:col-span-3">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-app-text">Map your CSV columns</h2>
                        @if ($source === 'preset')
                            <x-ui.badge color="primary">Previously confirmed mapping</x-ui.badge>
                        @elseif ($source === 'ai')
                            <x-ui.badge color="success">Matched by AI</x-ui.badge>
                        @endif
                    </div>

                    @if ($source === 'preset')
                        <p class="text-xs text-muted">These columns match a CSV you've imported before — the mapping you confirmed then is pre-filled. Review &amp; confirm.</p>
                    @endif

                    @if ($notice)
                        <div class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-app-text">{{ $notice }}</div>
                    @endif

                    <p class="text-xs text-muted"><span class="font-medium text-app-text">Name</span> and <span class="font-medium text-app-text">Email</span> are required, plus <span class="font-medium text-app-text">Job Title</span> or <span class="font-medium text-app-text">Bio</span>.</p>

                    @php
                        // Collapsed view shows the first 3 fields (Name, Job Title, Email —
                        // the compulsory set); the rest sit behind a toggle. Tell the user
                        // how many hidden fields the AI already mapped so nothing feels lost.
                        $hiddenKeys = array_slice(array_keys($fields), 3);
                        $hiddenMapped = count(array_filter($hiddenKeys, fn ($k) => ! empty($mapping[$k])));
                    @endphp
                    <div x-data="{ showAll: false }" class="divide-y divide-line rounded-lg border border-line">
                        @foreach ($fields as $key => $label)
                            <div class="grid grid-cols-1 items-center gap-1 px-4 py-2 sm:grid-cols-2 sm:gap-3" wire:key="map-{{ $key }}"
                                 @if ($loop->index >= 3) x-show="showAll" x-cloak @endif>
                                <label for="map-{{ $key }}" class="text-sm text-app-text">
                                    {{ $label }}
                                    @if (in_array($key, ['name', 'email'], true))
                                        <span class="text-danger">*</span>
                                    @elseif (in_array($key, ['jobTitle', 'summary'], true))
                                        <span class="text-warning" title="Job Title or Bio is required">•</span>
                                    @endif
                                </label>
                                <x-ui.select id="map-{{ $key }}" wire:model.live="mapping.{{ $key }}" class="text-sm">
                                    <option value="">— Not mapped —</option>
                                    @foreach ($headers as $column)
                                        <option value="{{ $column }}">{{ $column }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        @endforeach
                        <button type="button" @click="showAll = ! showAll"
                                class="flex w-full items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-medium text-primary transition hover:bg-primary-soft/40">
                            <span x-show="! showAll">Show all fields ({{ count($hiddenKeys) }} more{{ $hiddenMapped ? " · {$hiddenMapped} mapped" : '' }})</span>
                            <span x-show="showAll" x-cloak>Show fewer fields</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="showAll ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </div>

                    @if ($this->qualityWarning)
                        <div class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-app-text">{{ $this->qualityWarning }}</div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <x-ui.button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
                            <span wire:loading.remove wire:target="confirm">Confirm &amp; start Analyze</span>
                            <span wire:loading wire:target="confirm">Starting import…</span>
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @endif
    </div>
</div>
