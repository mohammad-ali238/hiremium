<?php

namespace App\Filament\Admin\Resources\ToolRuns;

use App\Models\ToolRun;
use App\Models\ToolRunEvent;
use App\Models\ToolRunFile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Downloading the file a free-tool run was produced from.
 *
 * Shared by Tool Runs and Tool Usage Logs so the two behave identically — a log
 * line is only ever useful next to the document that produced it.
 *
 * Not gated by ToolSettings::allowCvDownload(): that switch governs whether the
 * public result page exposes the CV to whoever holds the share link, which is a
 * different question from whether an admin can audit it.
 */
class ToolRunFileActions
{
    /** For a table of runs. */
    public static function forRun(): Action
    {
        return self::build(fn (ToolRun $record): ?ToolRun => $record);
    }

    /** For a table of per-stage log lines, which hang off a run. */
    public static function forEvent(): Action
    {
        return self::build(fn (ToolRunEvent $record): ?ToolRun => $record->run);
    }

    private static function build(callable $toRun): Action
    {
        return Action::make('download_file')
            ->label('File')
            ->tooltip('Download the file this run was given')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn ($record): bool => self::file($toRun($record)) !== null)
            ->action(function ($record) use ($toRun) {
                $file = self::file($toRun($record));

                if ($file === null || ! Storage::disk($file->disk)->exists($file->path)) {
                    Notification::make()
                        ->title('That file is no longer on disk')
                        ->body('Free-tool uploads are purged on their retention schedule; the log line is kept.')
                        ->danger()
                        ->send();

                    return null;
                }

                return Storage::disk($file->disk)->download($file->path, $file->original_name);
            });
    }

    private static function file(?ToolRun $run): ?ToolRunFile
    {
        return $run?->primaryFile();
    }
}
