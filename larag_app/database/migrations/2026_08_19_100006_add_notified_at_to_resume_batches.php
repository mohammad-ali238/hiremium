<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Batch finished" must be sent at most ONCE per batch. Tracking it by a
 * status transition re-fired the email after a resume (processing → terminal
 * again); a timestamp makes it idempotent. Also adds the 'waiting_credits'
 * batch status (only credit-stalled files remain — not a real failure).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->timestamp('completed_notified_at')->nullable()->after('failed_count');
            $table->timestamp('credits_notified_at')->nullable()->after('completed_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->dropColumn(['completed_notified_at', 'credits_notified_at']);
        });
    }
};
