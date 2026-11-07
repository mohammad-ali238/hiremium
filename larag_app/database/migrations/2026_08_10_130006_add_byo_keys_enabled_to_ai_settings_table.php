<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin master switch: allow account-level BYO provider keys. Off by default
 * (platform keys + credits). Mirrors config('hiremium.ai.byo_keys_enabled').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('byo_keys_enabled')->default(false)->after('gemini_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('byo_keys_enabled');
        });
    }
};
