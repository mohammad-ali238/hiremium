<?php

namespace App\Filament\Admin\Resources\PipelineUsageLogs;

use App\Models\PipelineUsageLog;
use App\Models\ResumeFile;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;

/**
 * Row actions shared by the Pipeline Usage Logs screen and the per-batch usage
 * relation manager, so the two never drift apart.
 *
 * The detail modal is built from Filament infolist components rather than a
 * Blade view of Tailwind classes: the admin panel ships its own compiled CSS,
 * so app utility classes simply do not exist inside it — which is what left
 * the old modal unstyled and unreadable.
 */
class PipelineUsageLogActions
{
    public static function view(): Action
    {
        return Action::make('details')
            ->label('View')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (PipelineUsageLog $record): string => 'Usage log #'.$record->id)
            ->modalDescription(fn (PipelineUsageLog $record): string => ucfirst($record->operation)
                .' on the '.$record->lane.' lane · '.$record->created_at?->format('M j, Y H:i:s'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->infolist(self::detail());
    }

    /** Streams the CV this log was produced from. */
    public static function downloadCv(): Action
    {
        return Action::make('download_cv')
            ->label('CV')
            ->tooltip('Download the CV this call ran on')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (PipelineUsageLog $record): bool => self::file($record) !== null)
            ->action(function (PipelineUsageLog $record) {
                $file = self::file($record);

                if ($file === null || ! Storage::disk($file->disk)->exists($file->storage_key)) {
                    Notification::make()
                        ->title('That CV is no longer on disk')
                        ->body('The log row is kept for accounting, but the file itself has been purged.')
                        ->danger()
                        ->send();

                    return null;
                }

                return Storage::disk($file->disk)->download($file->storage_key, $file->original_name);
            });
    }

    /**
     * Usage logs are immutable and outlive the CVs they describe, so look the
     * file up including soft-deleted ones — a user deleting a batch should not
     * stop an admin inspecting what was charged for.
     */
    private static function file(PipelineUsageLog $log): ?ResumeFile
    {
        if ($log->resume_file_id === null) {
            return null;
        }

        return ResumeFile::withTrashed()->find($log->resume_file_id);
    }

    /** @return array<int, mixed> */
    private static function detail(): array
    {
        return [
            Section::make('Call')
                ->columns(3)
                ->schema([
                    TextEntry::make('created_at')->label('When')->dateTime('M j, Y H:i:s'),
                    TextEntry::make('operation')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                        ->color(fn (string $state): string => $state === PipelineUsageLog::OP_COMBINED ? 'info' : 'gray'),
                    TextEntry::make('lane')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                        ->color(fn (string $state): string => $state === PipelineUsageLog::LANE_BATCH ? 'info' : 'gray'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                        ->color(fn (string $state): string => $state === PipelineUsageLog::STATUS_SUCCESS ? 'success' : 'danger'),
                    TextEntry::make('workspace.name')
                        ->label('Workspace')
                        ->placeholder('—')
                        ->suffix(fn (PipelineUsageLog $record): string => ' (#'.$record->workspace_id.')'),
                    TextEntry::make('id')->label('Log ID')->prefix('#'),
                ]),

            Section::make('AI')
                ->columns(3)
                ->schema([
                    TextEntry::make('ai_provider')->label('Provider')->placeholder('—'),
                    TextEntry::make('ai_model')->label('Model')->placeholder('—')->copyable(),
                    TextEntry::make('tokens_used')->label('Tokens')->numeric()->placeholder('—'),
                    TextEntry::make('credits_charged')->label('Credits charged')->numeric(),
                    TextEntry::make('duration_ms')
                        ->label('Duration')
                        ->placeholder('—')
                        ->state(fn (PipelineUsageLog $record): ?string => $record->duration_ms === null
                            ? null
                            : number_format($record->duration_ms).' ms'),
                    TextEntry::make('score')
                        ->label('Score')
                        ->placeholder('—')
                        ->badge()
                        ->color(fn (?int $state): string => match (true) {
                            $state === null => 'gray',
                            $state >= 70 => 'success',
                            $state >= 40 => 'warning',
                            default => 'danger',
                        }),
                ]),

            Section::make('Source')
                ->columns(3)
                ->schema([
                    TextEntry::make('resume_batch_id')->label('Batch')->placeholder('—')->prefix('#'),
                    TextEntry::make('resume_file_id')->label('File ID')->placeholder('—')->prefix('#'),
                    TextEntry::make('file_name')
                        ->label('File')
                        ->placeholder('—')
                        ->state(fn (PipelineUsageLog $record): ?string => self::file($record)?->original_name)
                        ->helperText(fn (PipelineUsageLog $record): ?string => self::file($record)?->trashed()
                            ? 'Deleted by the user — the log is kept for accounting.'
                            : null),
                ]),

            Section::make('Error')
                ->visible(fn (PipelineUsageLog $record): bool => filled($record->error_message))
                ->schema([
                    TextEntry::make('error_message')
                        ->hiddenLabel()
                        ->color('danger')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
