<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            // When set, the subscription this order belongs to has been cancelled
            // (kept on the order row itself instead of a separate ledger entry).
            $table->timestamp('cancelled_at')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
