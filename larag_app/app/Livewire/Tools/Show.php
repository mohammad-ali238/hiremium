<?php

namespace App\Livewire\Tools;

use App\Models\ToolRun;
use App\Rules\Recaptcha;
use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\Refusal;
use App\Services\Tools\Support\JobPayload;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolGuard;
use App\Services\Tools\ToolQuota;
use App\Services\Tools\ToolRegistry;
use App\Services\Tools\ToolSubmitter;
use App\Support\SiteSettings;
use App\Support\ToolSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * /tools/{tool} — the tool's page: its form, every gate, and the "check your
 * inbox" screen.
 *
 * Only the slug is component state: the definition is a service object, so it
 * is resolved from the registry on demand (Livewire cannot serialise it).
 *
 * Nothing is analysed here. A valid submission is stored and the visitor is
 * emailed a link; opening that link both proves the address and starts the run.
 */
class Show extends Component
{
    use WithFileUploads;

    /** Set from the URL on mount; nothing in the form may change which tool this is. */
    #[Locked]
    public string $slug = '';

    /** Field values, keyed by the tool's own field schema. */
    public array $form = [];

    public string $email = '';

    public bool $consent = false;

    public bool $marketing = false;

    public string $recaptcha = '';

    /** Honeypot: hidden from people, irresistible to bots. */
    public string $trap = '';

    /**
     * When the form was rendered — a submit 3 seconds later is not a human.
     * Locked, or the script being caught simply backdates it.
     */
    #[Locked]
    public int $startedAt = 0;

    public $resume;

    /* -- After a successful submission ------------------------------------ */

    public bool $submitted = false;

    /**
     * The run this page just created. Locked: resend() looks a run up by this
     * value, so an unlocked one lets anyone holding a uuid have us email its
     * owner on demand.
     */
    #[Locked]
    public string $runUuid = '';

    #[Locked]
    public string $maskedEmail = '';

    public bool $emailSent = true;

    public int $resendCount = 0;

    public string $resendNotice = '';

    /** Page-level refusal (a gate said no), shown as a banner above the form. */
    public string $refusal = '';

    public function mount(ToolRegistry $registry, string $tool): void
    {
        abort_if($registry->findEnabled($tool) === null, 404);

        $this->slug = $tool;
        $this->startedAt = now()->timestamp;

        foreach ($this->tool()->fields() as $key => $field) {
            $this->form[$key] = ($field['type'] ?? 'text') === 'tags' ? [] : '';
        }
    }

    #[Computed]
    public function tool(): ToolDefinition
    {
        $tool = app(ToolRegistry::class)->findEnabled($this->slug);

        abort_if($tool === null, 404);

        return $tool;
    }

    #[Computed]
    public function recaptchaEnabled(): bool
    {
        return ToolSettings::recaptchaEnabled();
    }

    #[Computed]
    public function recaptchaSiteKey(): string
    {
        return SiteSettings::recaptchaSiteKey();
    }

    /** The upload slot ('resume' for tool #1) and its rules. @return array<string, mixed> */
    #[Computed]
    public function fileSlot(): array
    {
        return (array) (array_values($this->tool()->files())[0] ?? []);
    }

    protected function rules(): array
    {
        $rules = [];

        foreach ($this->tool()->fields() as $key => $field) {
            $fieldRules = (array) ($field['rules'] ?? []);

            // A select can only ever hold one of the options it was rendered with.
            if (($field['type'] ?? '') === 'select' && ! empty($field['options'])) {
                $fieldRules[] = Rule::in(array_keys($field['options']));
            }

            $rules['form.'.$key] = $fieldRules;

            if (! empty($field['item_rules'])) {
                $rules['form.'.$key.'.*'] = (array) $field['item_rules'];
            }
        }

        // Only tools that declare a file slot ask for one. A text-only tool (the
        // Job Description Analyzer) has none, and must not demand a phantom file.
        if ($this->tool()->files() !== []) {
            $slot = $this->fileSlot();
            $rules['resume'] = [
                ($slot['required'] ?? true) ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', (array) ($slot['extensions'] ?? ['pdf'])),
                'max:'.((int) ($slot['max_mb'] ?? 2) * 1024),
            ];
        }

        $rules['email'] = ['required', 'email', 'max:190'];
        $rules['consent'] = ['accepted'];

        if ($this->recaptchaEnabled()) {
            $rules['recaptcha'] = ['required', new Recaptcha];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        $attributes = ['email' => 'email address', 'recaptcha' => 'captcha'];

        foreach ($this->tool()->fields() as $key => $field) {
            $attributes['form.'.$key] = mb_strtolower((string) ($field['label'] ?? $key));
        }

        $attributes['resume'] = mb_strtolower((string) ($this->fileSlot()['label'] ?? 'file'));

        return $attributes;
    }

    protected function messages(): array
    {
        return [
            'consent.accepted' => 'Please agree before continuing.',
            'resume.max' => 'That file is too large. Please upload a smaller one.',
            'resume.mimes' => 'Please upload a :attribute in one of these formats: '
                .strtoupper(implode(', ', (array) ($this->fileSlot()['extensions'] ?? []))).'.',
        ];
    }

    public function submit(ToolGuard $guard, ToolSubmitter $submitter)
    {
        $this->refusal = '';

        $context = (new ToolContext(
            tool: $this->tool(),
            email: $this->email,
            fillSeconds: max(0, now()->timestamp - $this->startedAt),
            trap: $this->trap,
        ))->withRequest(request());

        // Gates that need no valid input: switches, blocked IP, the per-IP rate
        // limit and the bot traps. Cheap, so they run before validation.
        if ($refusal = $guard->preCheck($context)) {
            return $this->refuse($refusal);
        }

        // A captcha token is good once, and for two minutes. Verifying it while
        // another field is still wrong spends it on a submission that cannot
        // succeed: the visitor fixes the field, submits the same token, and is
        // told the captcha failed while the box in front of them is still
        // ticked — with no way to re-tick a solved widget short of a reload.
        // So the fields are settled first, and the token is only spent on a
        // submission that is otherwise complete.
        $rules = $this->rules();
        $captchaRules = Arr::pull($rules, 'recaptcha');

        $this->validate($rules);

        if ($captchaRules !== null) {
            try {
                $this->validate(['recaptcha' => $captchaRules]);
            } catch (ValidationException $e) {
                // Spent, stale or refused — either way this token is dead, and
                // a retry that re-sends it fails for a second, wrong reason.
                $this->resetCaptcha();

                throw $e;
            }
        }

        // The email is only trustworthy after validation, so the identity and
        // payload gates run here — with the hashes that identify this exact
        // resume-against-this-exact-job.
        $context->setEmail($this->email);

        $normalized = JobPayload::normalize($this->tool(), $this->form);
        $context->inputHash = JobPayload::hash($normalized);
        // A text-only tool uploads nothing, so its identity is the payload alone.
        $context->fileHash = $this->resume !== null
            ? (hash_file('sha256', $this->resume->getRealPath()) ?: null)
            : null;
        $context->comboHash = JobPayload::comboHash($context->inputHash, $context->fileHash);

        // A complete submission: this one counts against the per-IP window.
        $guard->recordAttempt($context);

        // The ceilings and the row they govern are decided together: a COUNT
        // that another request can invalidate before the INSERT lands is not a
        // limit at all. See ToolQuota::decide().
        try {
            $outcome = app(ToolQuota::class)->decide(
                fn () => $guard->check($context)
                    ?? $submitter->submit($context, $normalized, $this->resume, $this->marketing)
            );
        } catch (LockTimeoutException) {
            return $this->refuse(new Refusal(
                'gate_busy',
                'Our free tools are unusually busy right now. Please try again in a little while.'
            ));
        }

        if ($outcome instanceof Refusal) {
            return $this->refuse($outcome);
        }

        $run = $outcome;

        $this->emailSent = $submitter->sendResultLink($run, $this->tool());
        $this->runUuid = $run->uuid;
        $this->maskedEmail = $this->maskEmail($run->email);
        $this->submitted = true;

        // Nothing typed is needed any more, and the temp upload should not sit
        // in storage while the success screen is open.
        $this->reset(['form', 'email', 'consent', 'marketing', 'recaptcha', 'resume', 'trap']);
    }

    /** "Didn't get the email?" — capped per run and to one a minute. */
    public function resend(ToolSubmitter $submitter): void
    {
        $this->resendNotice = '';

        $run = ToolRun::query()->where('uuid', $this->runUuid)->first();

        if ($run === null) {
            return;
        }

        if ($run->email_resent_count >= ToolSettings::resendMax()) {
            $this->resendNotice = "We've already re-sent this a few times. Please check your spam folder, or start a new check.";

            return;
        }

        // The per-run cap alone lets a fresh submission buy a fresh batch of
        // resends, so an address could be mailed far more than the run limit
        // suggests. This ceiling is on the address itself, across all of its
        // runs today — the real bound on how many emails one inbox receives.
        if (app(ToolQuota::class)->resendsToday($run->email_canonical) >= ToolSettings::resendMaxPerEmail()) {
            $this->resendNotice = "We've sent this address several links today. Please check your spam folder, or try again tomorrow.";

            return;
        }

        $key = 'tools:resend:'.$run->uuid;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->resendNotice = 'Please wait a minute before asking for another email.';

            return;
        }

        RateLimiter::hit($key, 60);

        if (! $submitter->sendResultLink($run, $this->tool())) {
            $this->emailSent = false;
            $this->resendNotice = "We couldn't send the email just now. Please try again in a moment.";

            return;
        }

        $run->increment('email_resent_count');

        $this->emailSent = true;
        $this->resendCount = (int) $run->email_resent_count;
        $this->resendNotice = 'Sent. It should arrive within a minute.';
    }

    /**
     * Show a refusal where it belongs — on the field a visitor can fix, or as a
     * banner when there is nothing to fix — and clear the captcha so the form
     * can be submitted again.
     */
    private function refuse(Refusal $refusal): void
    {
        if ($refusal->field !== null) {
            $this->addError($refusal->field, $refusal->message);
        } else {
            $this->refusal = $refusal->message;
        }

        $this->resetCaptcha();
    }

    /**
     * Drop the token we hold and hand the widget a fresh challenge. Both halves
     * matter: clearing the property alone leaves a solved-looking checkbox that
     * re-sends a dead token, and resetting the widget alone leaves the stale
     * value on the server.
     */
    private function resetCaptcha(): void
    {
        $this->recaptcha = '';
        $this->dispatch('recaptcha-reset');
    }

    /** j••••n@gmail.com — enough to recognise, not enough to leak. */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($local, 0, 1);
        $tail = mb_strlen($local) > 2 ? mb_substr($local, -1) : '';

        return $visible.str_repeat('•', max(3, mb_strlen($local) - 2)).$tail.'@'.$domain;
    }

    public function render()
    {
        return view('livewire.tools.show')
            ->layout('components.layouts.tool-page', [
                'title' => $this->tool()->name(),
                // Admin overrides layered over the tool's own defaults.
                'seo' => ToolSettings::seoFor($this->slug, $this->tool()->seoDefaults()),
            ]);
    }
}
