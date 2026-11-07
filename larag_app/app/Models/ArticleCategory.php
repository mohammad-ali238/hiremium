<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A blog topic. Flat by design — one category per article, no nesting and no
 * pivot, so an article can never appear under two URLs.
 */
class ArticleCategory extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (ArticleCategory $category) {
            $slug = Str::slug((string) $category->slug ?: (string) $category->name);

            // Str::slug() returns '' for a fully non-latin name, which would
            // collide on the unique index for the second such category.
            $category->slug = $slug !== '' ? $slug : 'category-'.Str::lower(Str::random(8));
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
