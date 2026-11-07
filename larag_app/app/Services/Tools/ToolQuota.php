<?php

namespace App\Services\Tools;

use App\Models\ToolDataHash;
use App\Models\ToolRun;
use App\Support\ToolSettings;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Counts the day's free runs and decides whose allowance a submission spends.
 *
 * Four independent ceilings, because one person has more than one identity:
 *   - per canonical email  (defeats +tag / gmail-dot aliases)
 *   - per device           (defeats "just use another email address")
 *   - per IP               (defeats "another address on another browser")
 *   - site-wide            (protects the token bill)
 *
 * Counts come straight from indexed `aitools_runs` queries, so they stay
 * correct after the cleanup sweeps delete old rows.
 */
class ToolQuota
{
    /** The gate key. One at a time, across every tool and every identity. */
    private const LOCK_KEY = 'tools:quota-gate';

    /** Long enough for a decision plus the insert it authorises; short enough that a crash frees it. */
    private const LOCK_SECONDS = 15;

    /**
     * Decide and act as one step.
     *
     * Every ceiling here is a COUNT that some later INSERT depends on. Left
     * unserialised, simultaneous submissions all read the same count, all pass,
     * and the day's limits are overshot by however many requests fit in the
     * gap. Taking turns removes the gap outright.
     *
     * A site-wide lock is the right size here precisely because the volume is
     * tiny — a few hundred runs a day — so contention is theoretical, and it
     * closes the email, device and site races together rather than needing a
     * lock per ceiling. Callers handle LockTimeoutException as "we are busy".
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     *
     * @throws LockTimeoutException
     */
    public function decide(Closure $callback): mixed
    {
        return Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS)
            ->block((int) config('tools.quota_lock_wait_seconds', 5), $callback);
    }

    /** Runs charged to one identity since midnight. */
    public function emailRunsToday(string $quotaEmail): int
    {
        if ($quotaEmail === '') {
            return 0;
        }

        return ToolRun::query()
            ->where('quota_email', $quotaEmail)
            ->where('created_at', '>=', $this->startOfDay())
            ->count();
    }

    /**
     * Runs submitted from one browser since midnight. A visitor with cookies
     * disabled has no device hash — the gate then simply cannot apply.
     */
    public function deviceRunsToday(?string $deviceHash): int
    {
        if (! filled($deviceHash)) {
            return 0;
        }

        return ToolRun::query()
            ->where('device_hash', $deviceHash)
            ->where('created_at', '>=', $this->startOfDay())
            ->count();
    }

    /**
     * Runs submitted from one IP since midnight. Behind a proxy this is only
     * as trustworthy as the trusted-proxy configuration, which is why it is
     * the last gate rather than the first.
     */
    public function ipRunsToday(?string $ipHash): int
    {
        if (! filled($ipHash)) {
            return 0;
        }

        return ToolRun::query()
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>=', $this->startOfDay())
            ->count();
    }

    /**
     * How many result emails have been re-sent to one address today, summed
     * across every run that address submitted. The per-run counter resets with
     * each new run, so only this sum bounds what a single inbox can be sent.
     */
    public function resendsToday(string $emailCanonical): int
    {
        if ($emailCanonical === '') {
            return 0;
        }

        return (int) ToolRun::query()
            ->where('email_canonical', $emailCanonical)
            ->where('created_at', '>=', $this->startOfDay())
            ->sum('email_resent_count');
    }

    /**
     * Runs that actually began work today, across all tools.
     *
     * Counted on `processing_started_at`, not `created_at`, because the cap
     * exists to guard the token bill and a submission is not a bill. A link
     * that was never opened costs nothing, so it must not use up somebody
     * else's turn — and a link submitted yesterday but opened today spends
     * today's tokens, so it belongs in today's count. The column is stamped
     * once, when the first stage runs, so a run is counted on exactly one day.
     */
    public function siteRunsStartedToday(): int
    {
        return ToolRun::query()
            ->whereNotNull('processing_started_at')
            ->where('processing_started_at', '>=', $this->startOfDay())
            ->count();
    }

    public function emailLimitReached(string $quotaEmail): bool
    {
        return $this->emailRunsToday($quotaEmail) >= ToolSettings::dailyPerEmail();
    }

    public function deviceLimitReached(?string $deviceHash): bool
    {
        if (! filled($deviceHash)) {
            return false;
        }

        return $this->deviceRunsToday($deviceHash) >= ToolSettings::dailyPerDevice();
    }

    /**
     * The ceiling that still stands when someone changes both their email
     * address and their browser. 0 means "no ceiling", as with the site cap —
     * and unlike the other two, that is a setting worth reaching for: one
     * office, campus or mobile network is a single address for everyone
     * behind it, so a low number here turns them all into one visitor.
     */
    public function ipLimitReached(?string $ipHash): bool
    {
        $cap = ToolSettings::dailyPerIp();

        if ($cap <= 0 || ! filled($ipHash)) {
            return false;
        }

        return $this->ipRunsToday($ipHash) >= $cap;
    }

    /**
     * Site cap of 0 means "no ceiling".
     *
     * Checked twice: once at submit, so we stop taking work we cannot do, and
     * again when a visitor presses the button, which is the moment the tokens
     * are actually spent.
     */
    public function siteCapReached(): bool
    {
        $cap = ToolSettings::siteDailyCap();

        return $cap > 0 && $this->siteRunsStartedToday() >= $cap;
    }

    /**
     * Whose daily allowance this submission spends.
     *
     * The same resume + job checked earlier today under a different address is
     * not a new question — it is the same question wearing a new email. The run
     * is charged back to the identity that asked it first, so swapping email
     * addresses buys nothing. `reusedFrom` is set when that happened.
     */
    public function resolveIdentity(ToolContext $context): void
    {
        $context->quotaEmail = $context->emailCanonical;
        $context->reusedFrom = null;

        if (! filled($context->comboHash)) {
            return;
        }

        $first = ToolDataHash::query()
            ->where('tool', $context->tool->slug())
            ->where('hash_type', ToolDataHash::TYPE_COMBO)
            ->where('hash', $context->comboHash)
            ->whereDate('used_date', Carbon::today())
            ->where('email_canonical', '!=', $context->emailCanonical)
            ->orderBy('id')
            ->first();

        if ($first === null) {
            return;
        }

        $context->quotaEmail = (string) $first->quota_email;
        $context->reusedFrom = (string) $first->email_canonical;
    }

    /** Write today's fingerprints so the next submission of the same data is recognised. */
    public function recordHashes(ToolRun $run, ToolContext $context): void
    {
        $rows = [
            ToolDataHash::TYPE_FILE => $context->fileHash,
            ToolDataHash::TYPE_PAYLOAD => $context->inputHash,
            ToolDataHash::TYPE_COMBO => $context->comboHash,
        ];

        foreach ($rows as $type => $hash) {
            if (! filled($hash)) {
                continue;
            }

            // The unique key is (tool, type, hash, date, email): a second run of
            // the same data by the same person today updates nothing new.
            ToolDataHash::query()->updateOrCreate(
                [
                    'tool' => $run->tool,
                    'hash_type' => $type,
                    'hash' => $hash,
                    'used_date' => Carbon::today()->toDateString(),
                    'email_canonical' => $context->emailCanonical,
                ],
                [
                    'quota_email' => $context->quotaEmail,
                    'aitools_run_id' => $run->id,
                    'created_at' => now(),
                ]
            );
        }
    }

    private function startOfDay(): Carbon
    {
        return Carbon::now()->startOfDay();
    }
}
