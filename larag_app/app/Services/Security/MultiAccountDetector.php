<?php

namespace App\Services\Security;

use App\Models\User;

/**
 * Post-login multi-account detection (same process as the hirin project). When one
 * browser device accumulates several accounts, flag them for admin review — and past
 * the restrict threshold, automatically block AI analysis on the NEWER accounts (the
 * oldest keeps working).
 *
 * Exempt from counting and from actions: trusted users (admin override), admins, and
 * accounts on an active paid plan (paying customers are not credit-farming).
 * Nothing is ever cleared automatically — only admins lift flags/restrictions.
 */
class MultiAccountDetector
{
    /** Distinct accounts on one device that trigger a review flag. */
    public const FLAG_THRESHOLD = 2;

    /** Distinct accounts on one device that auto-restrict the newer ones. */
    public const RESTRICT_THRESHOLD = 3;

    public function evaluateDevice(string $deviceHash): void
    {
        $users = $this->eligibleUsersOnDevice($deviceHash);

        if ($users->count() < self::FLAG_THRESHOLD) {
            return;
        }

        foreach ($users as $user) {
            if ($user->flagged_at === null) {
                $user->forceFill(['flagged_at' => now()])->saveQuietly();
            }
        }

        if ($users->count() < self::RESTRICT_THRESHOLD) {
            return;
        }

        // Oldest account stays active; restrict the rest.
        $users->slice(1)->each(function (User $user) {
            if ($user->restricted_at === null) {
                $user->forceFill(['restricted_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Distinct non-exempt users seen on this device (login history + registration
     * signal), oldest account first.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function eligibleUsersOnDevice(string $deviceHash): \Illuminate\Support\Collection
    {
        return User::query()
            ->where(function ($q) use ($deviceHash) {
                $q->where('device_hash', $deviceHash)
                    ->orWhereIn('id', fn ($sub) => $sub
                        ->select('user_id')
                        ->from('account_devices')
                        ->where('device_hash', $deviceHash));
            })
            ->whereNull('trusted_at')
            ->where('is_admin', false)
            // Active paid subscriptions are exempt — paying users are not farming
            // free credits.
            ->whereDoesntHave('subscriptions', fn ($q) => $q->active()
                ->whereHas('plan', fn ($p) => $p->where('price', '>', 0)))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
