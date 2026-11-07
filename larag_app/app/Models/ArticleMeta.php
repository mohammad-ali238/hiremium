<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-article SEO overrides. Every column is optional: an empty field falls
 * back to the article itself and then to the Blog Settings defaults, so a
 * post published without ever opening the SEO tab still has sane meta tags.
 */
class ArticleMeta extends Model
{
    protected $table = 'article_meta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
