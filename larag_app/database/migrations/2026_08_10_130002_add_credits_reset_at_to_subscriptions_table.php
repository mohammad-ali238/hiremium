<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the monthly credit allotment next refreshes. Free subscriptions (no
 * billing provider) are reset by the hiremium:reset-monthly-credits command;
 * paid ones continue to reset via the billing renewal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('credits_reset_at')->nullable()->after('credits_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('credits_reset_at');
        });
    }
};
