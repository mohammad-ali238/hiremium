<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a paid period ran out and we extended it to wait for the provider (a
 * late renewal webhook, or a card retry during dunning) instead of downgrading
 * the customer to Free. Set once per period, cleared when the renewal lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('renewal_grace_at')->nullable()->after('expired_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('renewal_grace_at');
        });
    }
};
