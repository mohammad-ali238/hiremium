<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free trial AI credits — one row per email address that has claimed a trial.
 * Separate from the paid subscriptions table on purpose (different lifecycle,
 * no user account required).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_credits', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('email_canonical')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('api_key', 80)->nullable()->unique();

            // Where the claim came from (soft trust — used for analytics/abuse review).
            $table->string('site_url', 255)->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->string('reported_ip', 45)->nullable();

            $table->string('status', 20)->default('pending')->index(); // pending|verified|expired|revoked
            $table->unsignedInteger('credits_total')->default(0);
            $table->unsignedInteger('credits_used')->default(0);

            // One-time redirect token handed back to the WP site after verification.
            $table->string('redirect_token', 64)->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_credits');
    }
};
