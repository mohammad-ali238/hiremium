<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title = rtrim(fake()->sentence(6), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'excerpt' => fake()->sentence(15),
            'content_mode' => Article::MODE_EDITOR,
            'content_editor' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'author_name' => fake()->name(),
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
        ];
    }

    /** Live now. */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    /** Published, but not until the given time. */
    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn (): array => [
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => $at ?? now()->addDay(),
        ]);
    }

    /** Body pasted as raw HTML. */
    public function html(string $html): static
    {
        return $this->state(fn (): array => [
            'content_mode' => Article::MODE_HTML,
            'content_html' => $html,
        ]);
    }
}
