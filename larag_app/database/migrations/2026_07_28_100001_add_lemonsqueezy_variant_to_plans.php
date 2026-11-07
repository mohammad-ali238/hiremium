<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // LemonSqueezy product variant this plan maps to (used to build the
            // checkout and to resolve a plan from an incoming webhook).
            $table->string('lemonsqueezy_variant_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('lemonsqueezy_variant_id');
        });
    }
};
