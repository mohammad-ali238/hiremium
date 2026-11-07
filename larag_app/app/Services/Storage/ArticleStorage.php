<?php

namespace App\Services\Storage;

use App\Support\BlogSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Where article images live. Same shape as CvStorage — a thin wrapper so no
 * call site ever names a disk — except that the disk is built from the Blog
 * Settings row rather than from a config file, and the media is public rather
 * than streamed through an authorised controller.
 */
class ArticleStorage
{
    /**
     * The only types that may be written, keyed by their DETECTED mime.
     *
     * The stored filename is built from this map, never from what the browser
     * called the file: the media folder sits inside the web root and is served
     * directly, so a trusted extension is the thing standing between an upload
     * and code execution. SVG is deliberately absent — it can carry script.
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    /** The safe extension for a detected mime, or null if the type is not allowed. */
    public static function extensionForMime(?string $mime): ?string
    {
        $extension = self::MIME_EXTENSIONS[(string) $mime] ?? null;

        return $extension !== null && in_array($extension, BlogSettings::allowedExtensions(), true)
            ? $extension
            : null;
    }

    /** Every mime the current settings accept. @return list<string> */
    public static function acceptedMimeTypes(): array
    {
        $allowed = BlogSettings::allowedExtensions();

        return array_values(array_unique(array_keys(array_filter(
            self::MIME_EXTENSIONS,
            fn (string $ext): bool => in_array($ext, $allowed, true),
        ))));
    }

    /** The disk uploads currently go to. */
    public function disk(): string
    {
        BlogSettings::registerDisks();

        return BlogSettings::storageDisk();
    }

    /**
     * Store one upload and return the columns describing it.
     *
     * @return array{disk: string, path: string, original_name: string, mime: string, size_bytes: int, width: int|null, height: int|null, sha256: string}
     */
    public function put(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $extension = self::extensionForMime($mime);

        if ($extension === null) {
            throw new RuntimeException('That file type is not allowed.');
        }

        $contents = (string) file_get_contents($file->getRealPath());

        $path = sprintf('articles/%s/%s.%s', now()->format('Y/m'), (string) Str::uuid(), $extension);
        $disk = $this->disk();

        Storage::disk($disk)->put($path, $contents, 'public');

        [$width, $height] = self::dimensions($file->getRealPath());

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit((string) $file->getClientOriginalName(), 250, ''),
            'mime' => $mime,
            'size_bytes' => strlen($contents),
            'width' => $width,
            'height' => $height,
            'sha256' => hash('sha256', $contents),
        ];
    }

    public function exists(string $disk, string $path): bool
    {
        BlogSettings::registerDisks();

        return Storage::disk($disk)->exists($path);
    }

    /** Remove a file, tolerating one that is already gone. */
    public function delete(string $disk, string $path): void
    {
        if ($disk === '' || $path === '') {
            return;
        }

        BlogSettings::registerDisks();

        Storage::disk($disk)->delete($path);
    }

    /** @return array{0: int|null, 1: int|null} */
    private static function dimensions(string $realPath): array
    {
        $size = @getimagesize($realPath);

        if ($size === false) {
            return [null, null];
        }

        return [
            $size[0] > 0 && $size[0] <= 65535 ? $size[0] : null,
            $size[1] > 0 && $size[1] <= 65535 ? $size[1] : null,
        ];
    }
}
