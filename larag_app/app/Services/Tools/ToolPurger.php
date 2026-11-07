<?php

namespace App\Services\Tools;

use App\Models\ToolDataHash;
use App\Models\ToolRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes free-tool runs and everything hanging off them.
 *
 * Two rules decide what goes: a link nobody ever opened dies at its expiry
 * (nothing was ever produced, so nothing is lost), and every run dies at the
 * retention date whether it was opened or not. Both are visitor data we
 * promised not to keep, so a purge takes the uploaded file with it — the row
 * alone would leave the CV sitting on disk forever.
 */
class ToolPurger
{
    /** Runs whose link was never opened inside its window. */
    public function expiredUnopened(): Builder
    {
        return ToolRun::query()->expiredUnopened();
    }

    /** Runs past the hard retention date, opened or not. */
    public function pastRetention(): Builder
    {
        return ToolRun::query()->purgeable();
    }

    public function expiredUnopenedCount(): int
    {
        return $this->expiredUnopened()->count();
    }

    public function pastRetentionCount(): int
    {
        return $this->pastRetention()->count();
    }

    public function purgeExpiredUnopened(int $limit = 500): int
    {
        return $this->purgeQuery($this->expiredUnopened(), $limit);
    }

    public function purgeRetention(int $limit = 500): int
    {
        return $this->purgeQuery($this->pastRetention(), $limit);
    }

    /** Delete one run: its file on disk, its day's hashes, then the row. */
    public function purgeRun(ToolRun $run): void
    {
        foreach ($run->files as $file) {
            // Best effort: a file already gone must not stop the row going too.
            Storage::disk($file->disk)->delete($file->path);
        }

        // The hash rows only point at the run (nullOnDelete), so they would
        // outlive it and keep answering "this CV was used today" forever.
        ToolDataHash::query()->where('aitools_run_id', $run->id)->delete();

        // Files and events cascade with the row.
        $run->delete();
    }

    private function purgeQuery(Builder $query, int $limit): int
    {
        $purged = 0;

        $query->with('files')->orderBy('id')->limit($limit)->get()
            ->each(function (ToolRun $run) use (&$purged): void {
                $this->purgeRun($run);
                $purged++;
            });

        return $purged;
    }
}
