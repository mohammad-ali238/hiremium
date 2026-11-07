<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable per-stage log: one row per stage attempt / AI call, holding
        // the provider + model actually used (inherited or per-tool override),
        // token counts and the failure detail. Never updated (created_at only).
        Schema::create('aitools_run_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aitools_run_id')->constrained('aitools_runs')->cascadeOnDelete();
            $table->string('tool', 64)->index();
            $table->string('stage', 32)->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('status', 20)->index();           // success|failed|skipped

            $table->string('provider', 32)->nullable();
            $table->string('model', 80)->nullable();
            $table->boolean('provider_inherited')->default(true);

            $table->unsignedInteger('tokens_prompt')->nullable();
            $table->unsignedInteger('tokens_completion')->nullable();
            $table->unsignedInteger('tokens_total')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();             // truncated request + raw response

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aitools_run_events');
    }
};
