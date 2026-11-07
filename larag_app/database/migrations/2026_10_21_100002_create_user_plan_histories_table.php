<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of a user's plan lifecycle: every assignment (admin, payment,
 * or the auto-assigned Free plan), renewal, plan change, cancellation, expiry
 * and deletion. Plan name/credits/price are snapshotted on each row so the
 * history stays readable after the plan or the subscription row is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_plan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Nullable: the history outlives the subscription row it describes.
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('plan_name');
            $table->unsignedInteger('credits_total')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_interval', 10)->nullable();
            // assigned | renewed | superseded | cancelled | resumed | expired | deleted
            $table->string('event', 20);
            // admin | payment | auto_free | cron | self | system
            $table->string('source', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['subscription_id', 'id']);
        });

        // Seed the history from the subscriptions that already exist: one
        // "assigned" row each, plus a terminal row for the ones no longer active.
        foreach (DB::table('subscriptions')->orderBy('id')->get() as $subscription) {
            $shared = [
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan_name_snapshot ?: 'Plan',
                'credits_total' => (int) ($subscription->credits_total ?? 0),
                'price' => $subscription->plan_price_snapshot ?? 0,
                'billing_interval' => $subscription->billing_interval,
                'source' => 'system',
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'assigned_at' => $subscription->starts_at,
                'note' => 'Backfilled from existing subscription',
            ];

            DB::table('user_plan_histories')->insert($shared + [
                'event' => 'assigned',
                'created_at' => $subscription->created_at ?: $subscription->starts_at,
            ]);

            $terminal = match ($subscription->status) {
                'cancelled' => 'cancelled',
                'expired' => 'expired',
                'superseded' => 'superseded',
                default => null,
            };

            if ($terminal !== null) {
                DB::table('user_plan_histories')->insert($shared + [
                    'event' => $terminal,
                    'cancelled_at' => in_array($terminal, ['cancelled', 'superseded'], true) ? $subscription->updated_at : null,
                    'expired_at' => $terminal === 'expired' ? $subscription->updated_at : null,
                    'created_at' => $subscription->updated_at ?: $subscription->starts_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plan_histories');
    }
};
