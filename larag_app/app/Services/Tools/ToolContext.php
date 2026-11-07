<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Support\AccountSignals;
use Illuminate\Http\Request;

/**
 * Everything the gates, the quota and the submitter need about one attempt:
 * who is submitting (email + the browser/IP signals behind it) and what they
 * are submitting (the payload hashes).
 *
 * `quotaEmail` starts as the submitter's own canonical email and is rewritten
 * by ToolQuota when the same data was already checked today under a different
 * address — the run is then charged to that first identity.
 */
class ToolContext
{
    public string $emailCanonical = '';

    public string $quotaEmail = '';

    public ?string $reusedFrom = null;

    public ?string $ip = null;

    public ?string $ipHash = null;

    public ?string $deviceHash = null;

    public ?string $userAgent = null;

    public ?string $referrer = null;

    public ?string $locale = null;

    public ?string $inputHash = null;

    public ?string $fileHash = null;

    public ?string $comboHash = null;

    public function __construct(
        public readonly ToolDefinition $tool,
        public string $email = '',
        /** Seconds between the form rendering and the submit (bot signal). */
        public int $fillSeconds = 0,
        /** Honeypot input: filled in = a bot. */
        public string $trap = '',
    ) {
        $this->setEmail($email);
    }

    public function setEmail(string $email): void
    {
        $this->email = trim($email);
        $this->emailCanonical = $this->email === '' ? '' : AccountSignals::canonicalEmail($this->email);
        $this->quotaEmail = $this->emailCanonical;
    }

    /** Capture the browser signals: same device cookie registration uses. */
    public function withRequest(Request $request): static
    {
        $this->ip = $request->ip();
        $this->ipHash = $this->ip !== null ? hash('sha256', $this->ip) : null;
        $this->deviceHash = AccountSignals::deviceHash($request->cookie(AccountSignals::DEVICE_COOKIE));
        $this->userAgent = mb_substr((string) $request->userAgent(), 0, 512) ?: null;
        $this->referrer = mb_substr((string) $request->headers->get('referer', ''), 0, 512) ?: null;
        $this->locale = mb_substr((string) $request->getPreferredLanguage(), 0, 10) ?: null;

        return $this;
    }

    /** The signals a run row stores for abuse review. @return array<string, mixed> */
    public function signals(): array
    {
        return [
            'ip' => $this->ip,
            'ip_hash' => $this->ipHash,
            'device_hash' => $this->deviceHash,
            'user_agent' => $this->userAgent,
            'referrer' => $this->referrer,
            'locale' => $this->locale,
        ];
    }
}
