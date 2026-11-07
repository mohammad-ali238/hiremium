<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">

    {{-- Heading --}}
    <div class="text-center">
        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->tool->iconPath() }}" />
            </svg>
        </span>
        <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900">{{ $this->tool->name() }}</h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">{{ $this->tool->tagline() }}</p>
    </div>

    @if ($submitted)

        {{-- ------------------------------------------------------------------
             Success: the run is stored, the link is on its way. Nothing is
             analysed until that link is opened.
        ------------------------------------------------------------------- --}}
        <div class="mt-12 rounded-2xl border border-slate-200 bg-white p-8 text-center sm:p-12">
            <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-green-50">
                <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </span>

            <h2 class="mt-6 text-2xl font-bold text-slate-900">Check your inbox</h2>
            <p class="mx-auto mt-3 max-w-md text-slate-600">
                We've sent your result link to <span class="font-semibold text-slate-900">{{ $maskedEmail }}</span>.
                Open it and your report starts building right away.
            </p>

            <p class="mx-auto mt-4 max-w-md text-sm text-slate-500">
                The link expires in {{ \App\Support\ToolSettings::expiryHours() }} hours if it's never opened.
                Once you open it, it's yours to keep and share.
            </p>

            @unless ($emailSent)
                <div class="mx-auto mt-6 max-w-md rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                    We couldn't send that email just now. Please use the button below to try again.
                </div>
            @endunless

            <div class="mt-8 border-t border-slate-100 pt-6">
                <p class="text-sm text-slate-500">Didn't get it? Check your spam folder first.</p>

                <button type="button" wire:click="resend" wire:loading.attr="disabled" wire:target="resend"
                        class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                    <svg wire:loading wire:target="resend" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    Send the email again
                </button>

                @if ($resendNotice)
                    <p class="mt-3 text-sm font-medium text-slate-600">{{ $resendNotice }}</p>
                @endif
            </div>
        </div>

        <p class="mt-8 text-center text-sm text-slate-500">
            Screening more than one resume?
            <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-500">Create a free Hiremium account</a>
            to upload CVs in bulk.
        </p>

    @else

        {{-- ------------------------------------------------------------------
             The form
        ------------------------------------------------------------------- --}}
        <form wire:submit="submit" data-tool-form class="mt-12 space-y-8">

            {{-- A gate said no: nothing on the form to fix, so it reads as a banner. --}}
            @if ($refusal)
                <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
                    <p class="text-sm font-medium text-amber-900">{{ $refusal }}</p>
                    <a href="{{ \App\Support\ToolSettings::contactUrl() }}"
                       class="mt-1 inline-block text-sm font-semibold text-amber-900 underline underline-offset-2 hover:text-amber-800">
                        Contact us
                    </a>
                </div>
            @endif

            {{-- Error summary: every problem in one place, each one a link to its field. --}}
            @if ($errors->any())
                <div class="rounded-xl bg-red-50 p-4 ring-1 ring-red-200">
                    <p class="text-sm font-semibold text-red-900">
                        Please fix {{ $errors->count() === 1 ? 'this' : 'these '.$errors->count().' things' }} and try again:
                    </p>
                    <ul class="mt-2 space-y-1 text-sm text-red-800">
                        @foreach ($errors->keys() as $key)
                            <li>
                                <a href="#field-{{ \Illuminate\Support\Str::slug(str_replace('.', '-', $key)) }}" class="underline decoration-red-300 underline-offset-2 hover:text-red-900">
                                    {{ $errors->first($key) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- The job — only for tools that ask about a role (the ATS checker has no fields). --}}
            @if (! empty($this->tool->fields()))
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">The job</h2>
                <p class="mt-1 text-sm text-slate-500">Describe the role you're hiring for, or the one you're applying to.</p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    @foreach ($this->tool->fields() as $key => $field)
                        @php
                            $id = 'field-form-'.\Illuminate\Support\Str::slug($key);
                            $error = $errors->first('form.'.$key);
                            $span = ($field['width'] ?? 'full') === 'half' ? 'sm:col-span-1' : 'sm:col-span-2';
                            $base = 'mt-1.5 block w-full rounded-lg border text-sm shadow-sm transition focus:ring-1 '
                                .($error ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500');
                        @endphp

                        <div class="{{ $span }}">
                            <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
                                {{ $field['label'] }}
                                @if (in_array('required', (array) ($field['rules'] ?? []), true))
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>

                            @switch($field['type'] ?? 'text')
                                @case('textarea')
                                    <textarea id="{{ $id }}" rows="{{ $field['rows'] ?? 6 }}"
                                              wire:model="form.{{ $key }}"
                                              placeholder="{{ $field['placeholder'] ?? '' }}"
                                              class="{{ $base }} px-3 py-2"></textarea>
                                    @break

                                @case('select')
                                    <select id="{{ $id }}" wire:model="form.{{ $key }}" class="{{ $base }} h-10 px-3">
                                        <option value="">Choose…</option>
                                        @foreach ((array) ($field['options'] ?? []) as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('tags')
                                    <div id="{{ $id }}" class="mt-1.5">
                                        <x-ui.tags :model="'form.'.$key" :placeholder="$field['placeholder'] ?? 'Type a skill and press Enter…'" />
                                    </div>
                                    @break

                                @case('number')
                                    <input id="{{ $id }}" type="number" inputmode="numeric"
                                           min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? 60 }}"
                                           wire:model="form.{{ $key }}"
                                           placeholder="{{ $field['placeholder'] ?? '' }}"
                                           class="{{ $base }} h-10 px-3">
                                    @break

                                @default
                                    <input id="{{ $id }}" type="text"
                                           wire:model="form.{{ $key }}"
                                           placeholder="{{ $field['placeholder'] ?? '' }}"
                                           class="{{ $base }} h-10 px-3">
                            @endswitch

                            @if ($error)
                                <p class="mt-1.5 text-xs font-medium text-red-600">{{ $error }}</p>
                            @elseif (! empty($field['help']))
                                <p class="mt-1.5 text-xs text-slate-500">{{ $field['help'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- The resume — only for tools that take a file (the JD Analyzer is text-only). --}}
            @if (! empty($this->tool->files()))
            @php $slot = $this->fileSlot; @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">{{ $slot['label'] ?? 'Resume' }}</h2>
                <p class="mt-1 text-sm text-slate-500">One file, {{ strtoupper(implode(', ', (array) ($slot['extensions'] ?? []))) }}.</p>

                <div id="field-resume" class="mt-5">
                    <label class="relative flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed bg-slate-50 px-6 py-10 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40
                                  {{ $errors->has('resume') ? 'border-red-300' : 'border-slate-300' }}">
                        <input type="file" wire:model="resume"
                               accept="{{ collect((array) ($slot['extensions'] ?? []))->map(fn ($e) => '.'.$e)->implode(',') }}"
                               class="absolute inset-0 cursor-pointer opacity-0">

                        <span class="grid h-12 w-12 place-items-center rounded-xl bg-white text-indigo-600 ring-1 ring-slate-200">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 9l5-5 5 5M12 4v12" />
                            </svg>
                        </span>

                        <span class="mt-3 text-sm font-medium text-slate-700" wire:loading.remove wire:target="resume">
                            @if ($resume)
                                {{ $resume->getClientOriginalName() }} — <span class="text-indigo-600">choose a different file</span>
                            @else
                                Drop your file here, or <span class="text-indigo-600">browse</span>
                            @endif
                        </span>

                        <span class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-indigo-600" wire:loading wire:target="resume">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            Uploading…
                        </span>
                    </label>

                    @error('resume')
                        <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            @endif

            {{-- Where to send it --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">Where should we send it?</h2>
                <p class="mt-1 text-sm text-slate-500">Your report opens from the link we email you — no account needed.</p>

                <div class="mt-5">
                    <label for="field-email" class="block text-sm font-medium text-slate-700">Email address <span class="text-red-500">*</span></label>
                    <input id="field-email" type="email" wire:model="email" autocomplete="email" placeholder="you@company.com"
                           class="mt-1.5 block h-10 w-full rounded-lg border px-3 text-sm shadow-sm transition focus:ring-1
                                  {{ $errors->has('email') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500' }}">
                    @error('email')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Honeypot: off-screen, not hidden, so scripted fillers still find it. --}}
                <div aria-hidden="true" class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden">
                    <label>Company website<input type="text" wire:model="trap" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" wire:model="consent"
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-slate-600">
                            {{ \App\Support\ToolSettings::consentText() }}
                            See the <a href="{{ route('privacy') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">Privacy Policy</a>.
                            <span class="text-red-500">*</span>
                        </span>
                    </label>
                    @error('consent')
                        <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror

                    @if (\App\Support\ToolSettings::marketingOptIn())
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" wire:model="marketing"
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-slate-600">Send me occasional hiring tips and product news. (Optional)</span>
                        </label>
                    @endif
                </div>

                @if ($this->recaptchaEnabled)
                    <div class="mt-5">
                        <div wire:ignore>
                            <div class="g-recaptcha" data-sitekey="{{ $this->recaptchaSiteKey }}"
                                 data-callback="hiremiumToolCaptcha" data-expired-callback="hiremiumToolCaptchaExpired"
                                 data-error-callback="hiremiumToolCaptchaExpired"></div>
                        </div>
                        @error('recaptcha')
                            <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="flex flex-col items-center gap-3">
                <button type="submit"
                        wire:loading.attr="disabled" wire:target="submit,resume"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    <svg wire:loading wire:target="submit" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="submit">{{ $this->tool->ctaLabel() }}</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
                <p class="text-xs text-slate-500">Free · no account · your report arrives by email</p>
            </div>
        </form>

        @if ($this->recaptchaEnabled)
            @assets
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @endassets

            @script
                <script>
                    // grecaptcha looks its callbacks up on window by the names in
                    // data-callback, so they have to be global — but they belong to
                    // this component. Defining them here means $wire is already the
                    // right component: nothing has to find it by selector, which is
                    // what used to break. (An escaped attribute selector cannot
                    // survive this block — Blade eats one backslash, JS drops the
                    // other, and closest('[wire:id]') then throws on the colon.)
                    window.hiremiumToolCaptcha = function (token) {
                        $wire.set('recaptcha', token, false);
                    };

                    window.hiremiumToolCaptchaExpired = function () {
                        $wire.set('recaptcha', '', false);
                    };

                    // A refused submission must be re-solvable: give a fresh challenge.
                    $wire.on('recaptcha-reset', function () {
                        if (window.grecaptcha) { window.grecaptcha.reset(); }
                    });
                </script>
            @endscript
        @endif

    @endif
</div>
