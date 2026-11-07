<?php

namespace App\Services\Billing;

use App\Exceptions\Api\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The workspace credit pool = monthly subscription credits (spent first) + a
 * persistent top-up pack balance. Wraps the existing CreditService for the
 * subscription side and records every movement to the ledger.
 */
class WorkspaceCredits
{
    public function __construct(private CreditService $creditService) {}

    public function wallet(Workspace $workspace): CreditWallet
    {
        return $workspace->creditWallet()->firstOrCreate([], ['pack_balance' => 0]);
    }

    public function monthlyAvailable(Workspace $workspace): int
    {
        return (int) ($workspace->ownerSubscription()?->credits_remaining ?? 0);
    }

    public function packAvailable(Workspace $workspace): int
    {
        return (int) $this->wallet($workspace)->pack_balance;
    }

    /** @return array{monthly:int, packs:int, total:int} */
    public function balance(Workspace $workspace): array
    {
        $monthly = $this->monthlyAvailable($workspace);
        $packs = $this->packAvailable($workspace);

        return ['monthly' => $monthly, 'packs' => $packs, 'total' => $monthly + $packs];
    }

    public function canAfford(Workspace $workspace, int $amount): bool
    {
        return $this->balance($workspace)['total'] >= $amount;
    }

    /**
     * Spend credits: monthly allotment first, then top-up packs. Atomic; throws
     * InsufficientCreditsException when the combined balance can't cover it.
     */
    /**
     * Idempotent variant for per-file pipeline charges: if the ledger already
     * holds an UNREFUNDED charge for this exact subject + reason, do nothing and
     * return false. Protects against the same job running twice (queue
     * re-delivery after retry_after, a worker killed between charge and refund,
     * manual/resume re-dispatch) — a file is charged at most once.
     *
     * @return bool true when a new charge was made, false when it was already charged
     */
    public function chargeOnce(Workspace $workspace, int $amount, string $reason, Model $subject, ?int $userId = null): bool
    {
        $net = (int) CreditTransaction::query()
            ->where('workspace_id', $workspace->id)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('reason', [$reason, $reason.' refund'])
            ->sum('amount');

        if ($net < 0) {
            return false; // a charge for this file is still outstanding (not refunded)
        }

        $this->charge($workspace, $amount, $reason, $subject, $userId);

        return true;
    }

    /**
     * Refund whatever is still outstanding for this subject + reason (net of any
     * earlier refunds). Idempotent — calling it twice refunds once.
     *
     * @return int credits refunded (0 when nothing was outstanding)
     */
    public function refundOutstanding(Workspace $workspace, string $reason, Model $subject, ?int $userId = null): int
    {
        $net = (int) CreditTransaction::query()
            ->where('workspace_id', $workspace->id)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('reason', [$reason, $reason.' refund'])
            ->sum('amount');

        if ($net >= 0) {
            return 0;
        }

        $this->refund($workspace, -$net, $reason.' refund', $subject, $userId);

        return -$net;
    }

    public function charge(Workspace $workspace, int $amount = 1, ?string $reason = null, ?Model $subject = null, ?int $userId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $monthlyAvail = $this->monthlyAvailable($workspace);
        $packAvail = $this->packAvailable($workspace);

        if ($monthlyAvail + $packAvail < $amount) {
            throw new InsufficientCreditsException;
        }

        $fromMonthly = min($monthlyAvail, $amount);
        $fromPacks = $amount - $fromMonthly;
        $walletId = $this->wallet($workspace)->id;

        DB::transaction(function () use ($workspace, $fromMonthly, $fromPacks, $walletId, $reason, $subject, $userId) {
            if ($fromMonthly > 0) {
                $this->creditService->charge($workspace->ownerSubscription(), $fromMonthly);
                $this->record($workspace, CreditTransaction::SOURCE_MONTHLY, -$fromMonthly, $reason, $subject, $userId);
            }

            if ($fromPacks > 0) {
                $wallet = CreditWallet::whereKey($walletId)->lockForUpdate()->firstOrFail();
                if ($wallet->pack_balance < $fromPacks) {
                    throw new InsufficientCreditsException;
                }
                $wallet->decrement('pack_balance', $fromPacks);
                $this->record($workspace, CreditTransaction::SOURCE_PACK, -$fromPacks, $reason, $subject, $userId);
            }
        });
    }

    /** Refund credits (e.g. an AI call failed after charging). Prefers the monthly pool. */
    public function refund(Workspace $workspace, int $amount = 1, ?string $reason = 'refund', ?Model $subject = null, ?int $userId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $subscription = $workspace->ownerSubscription();
        $walletId = $this->wallet($workspace)->id;

        DB::transaction(function () use ($workspace, $subscription, $walletId, $amount, $reason, $subject, $userId) {
            if ($subscription) {
                $this->creditService->refund($subscription, $amount);
                $this->record($workspace, CreditTransaction::SOURCE_MONTHLY, $amount, $reason, $subject, $userId);
            } else {
                $wallet = CreditWallet::whereKey($walletId)->lockForUpdate()->firstOrFail();
                $wallet->increment('pack_balance', $amount);
                $this->record($workspace, CreditTransaction::SOURCE_PACK, $amount, $reason, $subject, $userId);
            }
        });
    }

    /** Add purchased/granted pack credits to the workspace. */
    public function addPackCredits(Workspace $workspace, int $amount, ?string $reason = 'top-up'): void
    {
        if ($amount <= 0) {
            return;
        }

        $walletId = $this->wallet($workspace)->id;

        DB::transaction(function () use ($workspace, $walletId, $amount, $reason) {
            $wallet = CreditWallet::whereKey($walletId)->lockForUpdate()->firstOrFail();
            $wallet->increment('pack_balance', $amount);
            $this->record($workspace, CreditTransaction::SOURCE_PACK, $amount, $reason, null, null);
        });
    }

    private function record(Workspace $workspace, string $source, int $amount, ?string $reason, ?Model $subject, ?int $userId): void
    {
        CreditTransaction::create([
            'workspace_id' => $workspace->id,
            'user_id' => $userId ?? Auth::id(),
            'source' => $source,
            'amount' => $amount,
            'reason' => $reason,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'created_at' => now(),
        ]);
    }
}
