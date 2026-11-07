<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-operation credit costs applied specifically to FREE TRIAL calls (kept
 * separate from the paid parsing_credit_cost_* fields so trials can be cheaper,
 * e.g. 1 credit per operation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('trial_credit_cost_match')->default(1)->after('parsing_max_file_size_kb');
            $table->unsignedSmallInteger('trial_credit_cost_parse_text')->default(1)->after('trial_credit_cost_match');
            $table->unsignedSmallInteger('trial_credit_cost_parse_vision')->default(1)->after('trial_credit_cost_parse_text');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'trial_credit_cost_match',
                'trial_credit_cost_parse_text',
                'trial_credit_cost_parse_vision',
            ]);
        });
    }
};
