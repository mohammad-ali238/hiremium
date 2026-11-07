<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A blog post. The body lives in TWO columns, not one: `content_editor`
        // holds what the rich editor produced and `content_html` holds markup
        // pasted by hand, with `content_mode` choosing which one renders. That
        // way switching modes never destroys the other version — the rich
        // editor would otherwise strip markup it does not recognise.
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 190)->unique();          // 190 keeps the index inside utf8mb4 limits
            $table->string('title', 255);
            $table->string('excerpt', 500)->nullable();     // cards, RSS <description>, meta fallback
            $table->foreignId('article_category_id')->nullable()->constrained('article_categories')->nullOnDelete();

            $table->string('content_mode', 10)->default('editor');   // editor | html
            $table->longText('content_editor')->nullable();
            $table->longText('content_html')->nullable();
            $table->text('custom_css')->nullable();         // printed in <head> on this article only

            $table->string('cover_disk', 40)->nullable();   // recorded per row so a disk switch keeps old covers working
            $table->string('cover_path', 255)->nullable();
            $table->string('cover_alt', 255)->nullable();

            // A display string, not a FK: the byline must survive the author's
            // account being deleted, and the public page should not join users.
            $table->string('author_name', 120)->nullable();

            $table->string('status', 12)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();  // a future value schedules the post, no cron needed
            $table->unsignedTinyInteger('reading_minutes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
