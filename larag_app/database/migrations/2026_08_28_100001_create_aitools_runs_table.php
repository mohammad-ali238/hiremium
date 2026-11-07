<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per public Free Tool submission. Deliberately carries no FK
        // into workspace/user data — free-tool records are a separate world.
        Schema::create('aitools_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // the public result key
            $table->string('tool', 64)->index();            // registry slug

            $table->string('status', 20)->default('awaiting_open')->index();
            $table->string('stage', 32)->nullable();        // current/last stage key
            $table->unsignedTinyInteger('progress')->default(0);

            // Identity: who submitted vs whose daily quota the run is charged to
            // (reused data is charged to the email that first used it today).
            $table->string('email', 190);
            $table->string('email_canonical', 190)->index();
            $table->string('quota_email', 190)->index();
            $table->timestamp('consent_at')->nullable();
            $table->boolean('marketing_opt_in')->default(false);

            $table->string('ip', 45)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('device_hash', 64)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->string('locale', 10)->nullable();

            // Inputs: the tool's sanitized fields + the dedupe hashes.
            $table->json('input_data')->nullable();
            $table->char('input_hash', 64)->nullable()->index();
            $table->char('file_hash', 64)->nullable()->index();
            $table->char('combo_hash', 64)->nullable()->index();

            // Output: the complete report the result page renders.
            $table->json('output')->nullable();
            $table->unsignedSmallInteger('score')->nullable()->index();
            $table->string('band', 20)->nullable();

            $table->unsignedSmallInteger('ai_calls')->default(0);
            $table->unsignedInteger('ai_tokens_total')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('failed_stage', 32)->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->text('failure_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->timestamp('email_sent_at')->nullable();
            $table->unsignedTinyInteger('email_resent_count')->default(0);

            $table->timestamp('opened_at')->nullable()->index();
            $table->string('first_opened_ip', 45)->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();

            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // NULL once opened: an opened result never expires.
            $table->timestamp('expires_at')->nullable()->index();
            // created_at + retention days: the 90-day sweep target.
            $table->timestamp('purge_after')->nullable()->index();

            $table->timestamps();

            // Daily quota + site-cap counting.
            $table->index(['quota_email', 'created_at']);
            $table->index(['tool', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aitools_runs');
    }
};
