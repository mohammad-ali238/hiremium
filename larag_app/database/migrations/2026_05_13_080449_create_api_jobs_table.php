<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Public-safe job identifier
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->string('endpoint'); // e.g. matching-score.v1
            $table->string('status')->default('queued'); // queued | processing | completed | failed
            $table->unsignedSmallInteger('credits_charged');
            $table->unsignedSmallInteger('credits_refunded')->default(0);
            $table->json('input_meta')->nullable();   // safe meta only (no PII / no raw file bytes)
            $table->json('result')->nullable();       // final payload returned to client
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['endpoint', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_jobs');
    }
};
