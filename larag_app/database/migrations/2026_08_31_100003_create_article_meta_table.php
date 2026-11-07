<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-article SEO, one row per article. Named columns rather than
        // key/value rows so every field is typed and validatable; `extra`
        // carries anything added later without another migration.
        Schema::create('article_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->unique()->constrained('articles')->cascadeOnDelete();

            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 320)->nullable();   // 320, because silent truncation is worse than 65 spare bytes
            $table->string('meta_keywords', 255)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('robots', 60)->nullable();

            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('twitter_card', 30)->default('summary_large_image');
            $table->string('schema_type', 40)->default('BlogPosting');

            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_meta');
    }
};
