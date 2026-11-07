<?php

namespace App\Models;

use App\Services\Storage\ArticleStorage;
use App\Support\BlogSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One image uploaded for an article. The public URL is derived from disk +
 * path at read time rather than stored, so moving the blog to another domain
 * or another disk fixes these rows without a migration.
 */
class ArticleMedia extends Model
{
    protected $table = 'article_media';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Deleting a row through Eloquent takes its file with it. A force-deleted
        // ARTICLE is handled in ArticleObserver instead, because that path
        // cascades in the database and never fires model events.
        static::deleting(function (ArticleMedia $media): void {
            app(ArticleStorage::class)->delete((string) $media->disk, (string) $media->path);
        });
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** Absolute, because articles live in a subdirectory install. */
    public function getUrlAttribute(): string
    {
        return BlogSettings::mediaUrl((string) $this->disk, (string) $this->path);
    }

    /** e.g. "1.4 MB" — for the media panel. */
    public function getSizeForHumansAttribute(): string
    {
        $bytes = (int) $this->size_bytes;

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max(1, (int) ceil($bytes / 1024))).' KB';
    }
}
