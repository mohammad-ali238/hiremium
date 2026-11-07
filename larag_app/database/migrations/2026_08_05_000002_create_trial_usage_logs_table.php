<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usage log dedicated to free-trial AI credits (kept separate from usage_logs,
 * which records paid-subscription usage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trial_credit_id')->constrained('trial_credits')->cascadeOnDelete();
            $table->string('endpoint');
            $table->unsignedInteger('credits_consumed')->default(0);
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('status')->default('success'); // success|failed|refunded
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_usage_logs');
    }
};
