<?php

namespace App\Services\Credits;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\TrialCredit;
use App\Models\TrialUsageLog;
use App\Services\Billing\TrialCreditService;

/**
 * CreditSource backed by a free-trial grant. Charges the trial ledger and logs
 * to trial_usage_logs (kept separate from paid usage_logs).
 */
class TrialCreditSource implements CreditSource
{
    public function __construct(
        private readonly TrialCreditService $credits,
        private TrialCredit $trial,
    ) {}

    public function charge(int $credits): void
    {
        $this->trial = $this->credits->charge($this->trial, $credits);
    }

    public function refund(int $credits): void
    {
        $this->trial = $this->credits->refund($this->trial, $credits);
    }

    public function remaining(): int
    {
        return (int) $this->trial->credits_remaining;
    }

    public function ensureCanAfford(int $credits): void
    {
        if ($this->trial->credits_remaining < $credits) {
            throw new InsufficientCreditsException(
                "This call requires {$credits} free-trial credits but only {$this->trial->credits_remaining} remain."
            );
        }
    }

    public function logSuccess(string $endpoint, array $data): void
    {
        // usage_logs uses user_id/subscription_id keys; strip those and key on the trial.
        unset($data['user_id'], $data['subscription_id']);

        TrialUsageLog::create(array_merge([
            'trial_credit_id' => $this->trial->getKey(),
            'endpoint' => $endpoint,
            'status' => TrialUsageLog::STATUS_SUCCESS,
        ], $data));
    }

    public function logFailure(string $endpoint, array $requestPayload, string $error, ?string $ip = null): void
    {
        TrialUsageLog::create([
            'trial_credit_id' => $this->trial->getKey(),
            'endpoint' => $endpoint,
            'credits_consumed' => 0,
            'request_payload' => $requestPayload,
            'status' => TrialUsageLog::STATUS_REFUNDED,
            'error_message' => $error,
            'ip_address' => $ip,
        ]);
    }

    public function type(): string
    {
        return 'trial';
    }

    /**
     * The underlying grant (used by the status endpoint / resources).
     */
    public function trial(): TrialCredit
    {
        return $this->trial;
    }
}
