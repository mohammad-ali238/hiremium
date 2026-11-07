<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Thin, portable wrapper over the private CV disk. The disk name comes from
 * config('hiremium.storage.cv_disk') so a VPS move to S3/R2 is an env change.
 * Keys are structured by workspace + batch.
 */
class CvStorage
{
    public function disk(): string
    {
        return config('hiremium.storage.cv_disk', 'cv_local');
    }

    /** Store raw contents and return the storage key. */
    public function putContents(string $contents, int $workspaceId, int $batchId, string $ext): string
    {
        $key = sprintf('w%d/b%d/%s.%s', $workspaceId, $batchId, (string) Str::uuid(), strtolower($ext) ?: 'bin');

        Storage::disk($this->disk())->put($key, $contents);

        return $key;
    }

    public function exists(string $key): bool
    {
        return Storage::disk($this->disk())->exists($key);
    }

    public function delete(string $key): void
    {
        Storage::disk($this->disk())->delete($key);
    }
}
