<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Device signal (see App\Support\AccountSignals) + multi-account review
            // state: flagged (needs review), restricted (AI analysis blocked),
            // trusted (admin override — detector skips this user permanently).
            $table->string('device_hash', 64)->nullable()->index()->after('registration_ip');
            $table->timestamp('flagged_at')->nullable()->after('device_hash');
            $table->timestamp('restricted_at')->nullable()->after('flagged_at');
            $table->timestamp('trusted_at')->nullable()->after('restricted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['device_hash', 'flagged_at', 'restricted_at', 'trusted_at']);
        });
    }
};
