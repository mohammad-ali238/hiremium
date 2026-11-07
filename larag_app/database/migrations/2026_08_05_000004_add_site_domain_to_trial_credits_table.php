<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized site host for the trial grant, so per-site claim caps can be
 * enforced cheaply (indexed count).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_credits', function (Blueprint $table) {
            $table->string('site_domain', 253)->nullable()->index()->after('site_url');
        });
    }

    public function down(): void
    {
        Schema::table('trial_credits', function (Blueprint $table) {
            $table->dropColumn('site_domain');
        });
    }
};
