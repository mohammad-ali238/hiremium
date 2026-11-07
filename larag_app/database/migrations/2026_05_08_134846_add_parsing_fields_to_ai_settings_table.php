<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('parsing_ai_provider')->default('openai')->after('max_tokens');
            $table->string('parsing_openai_model')->default('gpt-4o-mini')->after('parsing_ai_provider');
            $table->string('parsing_gemini_model')->default('gemini-1.5-flash')->after('parsing_openai_model');
            $table->text('parsing_system_prompt')->nullable()->after('parsing_gemini_model');
            $table->decimal('parsing_temperature', 3, 2)->default(0.10)->after('parsing_system_prompt');
            $table->unsignedSmallInteger('parsing_credit_cost_text')->default(2)->after('parsing_temperature');
            $table->unsignedSmallInteger('parsing_credit_cost_vision')->default(5)->after('parsing_credit_cost_text');
            $table->unsignedInteger('parsing_max_file_size_kb')->default(5120)->after('parsing_credit_cost_vision');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'parsing_ai_provider',
                'parsing_openai_model',
                'parsing_gemini_model',
                'parsing_system_prompt',
                'parsing_temperature',
                'parsing_credit_cost_text',
                'parsing_credit_cost_vision',
                'parsing_max_file_size_kb',
            ]);
        });
    }
};
