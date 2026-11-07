<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->text('openai_api_key')->nullable();
            $table->text('gemini_api_key')->nullable();
            $table->string('default_provider')->default('openai'); // openai | gemini
            $table->string('default_model')->default('gpt-4o-mini');
            $table->text('system_prompt');
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedSmallInteger('max_tokens')->default(800);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
