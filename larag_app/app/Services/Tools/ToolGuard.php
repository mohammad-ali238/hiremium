<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Support\AccountSignals;
use App\Support\ToolSettings;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Every reason a free-tool submission can be turned away, checked in order and
 * returned as a Refusal (admin-readable code + a general visitor message).
 *
 * Split in two because reCAPTCHA and the field rules run in between: the cheap
 * signals that need no valid input are checked first (preCheck), the ones that
 * need a validated email and the payload hashes after (check).
 */
class ToolGuard
{
    /** A human needs at least this long to fill the form; faster is a script. */
    private const MIN_FILL_SECONDS = 3;

    public function __construct(private readonly ToolQuota $quota) {}

    /** Is this tool available at all right now? Used by the page, not just the submit. */
    public function checkAvailability(ToolDefinition $tool): ?Refusal
    {
        if (! ToolSettings::enabled() || ! ToolSettings::toolEnabled($tool->slug())) {
            return new Refusal(
                'tool_disabled',
                'This free tool is temporarily unavailable. Please check back a little later.'
            );
        }

        return null;
    }

    /**
     * Gates 1–4: switches, blocked IP, per-IP rate limit and the bot traps.
     * Everything here is judged on the request alone — no valid input needed.
     */
    public function preCheck(ToolContext $context): ?Refusal
    {
        if ($refusal = $this->checkAvailability($context->tool)) {
            return $refusal;
        }

        if ($context->ip !== null && in_array(mb_strtolower($context->ip), ToolSettings::blockedIps(), true)) {
            return new Refusal(
                'ip_blocked',
                "We can't process this submission. If you think this is a mistake, please contact us."
            );
        }

        // Livewire posts bypass route middleware, so the per-IP limit lives here.
        // Only complete submissions are counted (see recordAttempt) — someone
        // fixing typos on their own form is not a burst.
        if (RateLimiter::tooManyAttempts($this->rateKey($context), ToolSettings::perIpPerMinute())) {
            return new Refusal(
                'rate_limited',
                'That was a lot of submissions in a short time. Please wait a minute and try again.'
            );
        }

        // The honeypot is unambiguous: a real visitor never sees that input, so
        // nothing but a script can fill it.
        if (trim($context->trap) !== '') {
            return new Refusal('honeypot', 'Something went wrong. Please reload the page and try again.');
        }

        return null;
    }

    /**
     * Gates 5–9: email blocklists, then the four daily ceilings. Resolves the
     * identity the run is charged to as a side effect (see ToolQuota).
     */
    public function check(ToolContext $context): ?Refusal
    {
        // Fill time is only a bot signal once the form is COMPLETE. Checking it
        // earlier punishes the visitor who lands on the page and clicks submit
        // straight away to see what is required — they deserve the field errors,
        // not a mystery. A script that arrives with every field filled in under
        // three seconds is the case this catches.
        if ($context->fillSeconds < self::MIN_FILL_SECONDS) {
            return new Refusal('too_fast', 'Something went wrong. Please reload the page and try again.');
        }

        if ($refusal = $this->checkEmail($context)) {
            return $refusal;
        }

        if ($this->quota->siteCapReached()) {
            return new Refusal(
                'site_cap',
                'Our free tools are unusually busy right now. Please try again in a little while.'
            );
        }

        $this->quota->resolveIdentity($context);

        if ($this->quota->emailLimitReached($context->quotaEmail)) {
            // Someone else's data, and that person is out of runs for today —
            // say so plainly, since an honest visitor can fix it by using their
            // own email address.
            if ($context->reusedFrom !== null) {
                return new Refusal(
                    'data_reused',
                    'This resume and job have already been checked today using a different email address. '
                        .'If that was you, please submit with that same address — otherwise please try again tomorrow.',
                    'email'
                );
            }

            return new Refusal('email_quota', $this->dailyLimitMessage(), 'email');
        }

        // Same daily allowance, counted per browser. Deliberately the same
        // message as the email ceiling: a refusal should not reveal which
        // signal matched, or a second email address becomes the obvious try.
        if ($this->quota->deviceLimitReached($context->deviceHash)) {
            return new Refusal('device_quota', $this->dailyLimitMessage(), 'email');
        }

        // Last one standing: a new address typed into a fresh browser still
        // arrives from the same place. Same message again, for the same reason.
        if ($this->quota->ipLimitReached($context->ipHash)) {
            return new Refusal('ip_quota', $this->dailyLimitMessage(), 'email');
        }

        return null;
    }

    /**
     * Count one complete submission against the per-IP window. Called once the
     * input is valid, so failed validation never eats a visitor's allowance.
     */
    public function recordAttempt(ToolContext $context): void
    {
        RateLimiter::hit($this->rateKey($context), 60);
    }

    private function rateKey(ToolContext $context): string
    {
        return 'tools:submit:'.($context->ip ?? 'unknown');
    }

    /** Blocked address, disposable inbox, or an admin-banned domain. */
    private function checkEmail(ToolContext $context): ?Refusal
    {
        if (in_array($context->emailCanonical, ToolSettings::blockedEmails(), true)) {
            return new Refusal(
                'email_blocked',
                "We can't process this submission. If you think this is a mistake, please contact us.",
                'email'
            );
        }

        $domain = mb_strtolower(trim((string) strrchr($context->email, '@'), '@'));

        $banned = AccountSignals::isDisposable($context->email)
            || in_array($domain, ToolSettings::extraBannedDomains(), true);

        if ($banned) {
            return new Refusal(
                'email_domain_banned',
                'Please use a permanent email address — temporary inboxes are not supported, because your result link is sent by email.',
                'email'
            );
        }

        return null;
    }

    /**
     * One wording for every "you are out of runs today" case, on every tool.
     *
     * Deliberately says nothing about what the tool does: the registry is built
     * to host more of them, and copy naming this one would be wrong the day a
     * second is added.
     *
     * It also does not claim that signing up raises THIS allowance — it does
     * not. The free tools are guest-scoped and count the same for everyone, so
     * the account is described as the separate thing it actually is, and
     * someone already signed in is pointed at their dashboard rather than told
     * to register again.
     */
    private function dailyLimitMessage(): string
    {
        if (auth()->check()) {
            return "You've used all of today's free runs. Please try again tomorrow — your Hiremium workspace has its "
                .'own separate monthly allowance for screening CVs in bulk.';
        }

        return "You've used all of today's free runs. Please try again tomorrow. A free Hiremium account comes with "
            .'its own monthly AI allowance for screening CVs in bulk — no card needed.';
    }
}
