<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The subscription becomes the user's own plan store: the credit TOTAL is
 * snapshotted here at assignment/renewal instead of being read live from the
 * plans table (so editing a plan never rewrites an existing user's total), and
 * the lifecycle dates (cancelled/expired) + who assigned it are recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Credits granted for the current period — the denominator shown to
            // the user. Refreshed only on assignment, renewal or monthly reset.
            $table->unsignedInteger('credits_total')->nullable()->after('credits_remaining');
            // Plan name/price as they were when assigned (history survives plan edits/deletes).
            $table->string('plan_name_snapshot')->nullable()->after('credits_total');
            $table->decimal('plan_price_snapshot', 10, 2)->nullable()->after('plan_name_snapshot');
            // How this plan was assigned: admin | payment | auto_free | cron | system.
            $table->string('assign_source', 20)->nullable()->after('plan_price_snapshot');
            $table->foreignId('assigned_by')->nullable()->after('assign_source')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('assigned_by');
            $table->timestamp('expired_at')->nullable()->after('cancelled_at');
        });

        // Backfill the snapshot from each plan's current configuration so existing
        // subscriptions keep showing the same total they show today.
        foreach (DB::table('plans')->get() as $plan) {
            $yearly = (int) ($plan->yearly_credit_limit ?: (int) $plan->credit_limit * 12);

            DB::table('subscriptions')
                ->where('plan_id', $plan->id)
                ->where('billing_interval', 'yearly')
                ->update([
                    'credits_total' => $yearly,
                    'plan_name_snapshot' => $plan->name,
                    'plan_price_snapshot' => $plan->yearly_price ?? $plan->price,
                ]);

            DB::table('subscriptions')
                ->where('plan_id', $plan->id)
                ->where(function ($query) {
                    $query->whereNull('billing_interval')->orWhere('billing_interval', '!=', 'yearly');
                })
                ->update([
                    'credits_total' => (int) $plan->credit_limit,
                    'plan_name_snapshot' => $plan->name,
                    'plan_price_snapshot' => $plan->price,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn([
                'credits_total',
                'plan_name_snapshot',
                'plan_price_snapshot',
                'assign_source',
                'cancelled_at',
                'expired_at',
            ]);
        });
    }
};
