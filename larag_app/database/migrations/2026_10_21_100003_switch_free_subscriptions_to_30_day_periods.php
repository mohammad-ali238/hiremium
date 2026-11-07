<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Free subscriptions used to carry a far-future `ends_at` sentinel ("never
 * expires") and were refilled in place via `credits_reset_at`. They now run for
 * a real plan-duration period that is re-assigned when it ends, so convert the
 * existing rows: the end of the period becomes the date they were next due to
 * reset, and the in-place reset marker is cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        $freePlanIds = DB::table('plans')->where('price', '<=', 0)->pluck('duration_days', 'id');

        foreach ($freePlanIds as $planId => $durationDays) {
            $days = (int) ($durationDays ?: 30);

            $rows = DB::table('subscriptions')
                ->where('plan_id', $planId)
                ->where('status', 'active')
                ->where('ends_at', '>', now()->addYear())
                ->get(['id', 'starts_at', 'credits_reset_at']);

            foreach ($rows as $row) {
                $endsAt = $row->credits_reset_at
                    ?: Carbon::parse($row->starts_at)->addDays($days);

                DB::table('subscriptions')
                    ->where('id', $row->id)
                    ->update(['ends_at' => $endsAt, 'credits_reset_at' => null]);
            }
        }
    }

    public function down(): void
    {
        // The sentinel dates are not recoverable per-row; push active free
        // subscriptions back out to the old "never expires" horizon.
        $freePlanIds = DB::table('plans')->where('price', '<=', 0)->pluck('id');

        DB::table('subscriptions')
            ->whereIn('plan_id', $freePlanIds)
            ->where('status', 'active')
            ->update(['ends_at' => now()->addYears(10), 'credits_reset_at' => now()->addMonth()]);
    }
};
