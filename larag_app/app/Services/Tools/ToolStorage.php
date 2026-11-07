<?php

namespace App\Services\Tools;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Free-tool uploads on the same private disk recruiter CVs use, under their own
 * prefix (`tools/{tool}/…`) so the two worlds never share a folder and the
 * retention sweep can delete tool files without touching workspace data.
 */
class ToolStorage
{
    public function disk(): string
    {
        return config('hiremium.storage.cv_disk', 'cv_local');
    }

    /** Store an upload and return its key on the disk. */
    public function put(UploadedFile $file, string $tool): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension() ?: 'bin');

        $key = sprintf(
            '%s/%s/%s.%s',
            trim((string) config('tools.storage_prefix', 'tools'), '/'),
            $tool,
            (string) Str::uuid(),
            $extension
        );

        Storage::disk($this->disk())->put($key, $file->get());

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
