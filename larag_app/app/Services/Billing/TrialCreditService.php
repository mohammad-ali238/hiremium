<?php

namespace App\Services\Billing;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\TrialCredit;
use Illuminate\Support\Facades\DB;

/**
 * Atomic charge/refund of free-trial credits (credits_used on trial_credits).
 * Mirrors CreditService but for the trial ledger.
 */
class TrialCreditService
{
    public function charge(TrialCredit $trial, int $credits = 1): TrialCredit
    {
        return DB::transaction(function () use ($trial, $credits) {
            $fresh = TrialCredit::query()
                ->whereKey($trial->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->isUsable()) {
                throw new InsufficientCreditsException('Your free trial credits have expired or been used up.');
            }

            if ($fresh->credits_remaining < $credits) {
                throw new InsufficientCreditsException('Not enough free trial credits remaining for this call.');
            }

            $fresh->increment('credits_used', $credits);

            return $fresh->refresh();
        });
    }

    public function refund(TrialCredit $trial, int $credits = 1): TrialCredit
    {
        return DB::transaction(function () use ($trial, $credits) {
            $fresh = TrialCredit::query()
                ->whereKey($trial->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $newUsed = max(0, (int) $fresh->credits_used - $credits);
            $fresh->update(['credits_used' => $newUsed]);

            return $fresh->refresh();
        });
    }
}
