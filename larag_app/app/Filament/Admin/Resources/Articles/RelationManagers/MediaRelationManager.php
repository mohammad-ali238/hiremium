<?php

namespace App\Filament\Admin\Resources\Articles\RelationManagers;

use App\Models\Article;
use App\Models\ArticleMedia;
use App\Services\Storage\ArticleStorage;
use App\Support\BlogSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

/**
 * Images belonging to one article.
 *
 * It exists mainly for HTML mode, which has no uploader of its own: upload
 * here, copy the URL, paste it into the markup. The same images back the
 * cover, so there is only ever one place a picture lives.
 */
class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Media';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Media')
            ->description('Upload images here, then copy a URL to paste into the body. Copied URLs are absolute, so they keep working wherever the article is shown.')
            ->columns([
                // Not named 'url': a table keys its columns by name, so it would
                // collide with the copyable URL column below and disappear.
                ImageColumn::make('preview')
                    ->label('Preview')
                    ->state(fn (ArticleMedia $record): string => $record->url)
                    ->height(56),
                TextColumn::make('original_name')
                    ->label('File')
                    ->limit(40)
                    ->description(fn (ArticleMedia $record): string => $record->size_for_humans
                        .($record->width ? ' · '.$record->width.'×'.$record->height : '')),
                TextColumn::make('url')
                    ->label('URL')
                    ->copyable()
                    ->copyMessage('URL copied — paste it into your HTML')
                    ->limit(48)
                    ->tooltip(fn (ArticleMedia $record): string => $record->url),
                TextColumn::make('alt')
                    ->label('Alt text')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('upload')
                    ->label('Upload images')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalSubmitActionLabel('Upload')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Images')
                            ->multiple()
                            ->image()
                            // Keep the temporary files: ArticleStorage decides
                            // the name and the folder, from the DETECTED mime.
                            ->storeFiles(false)
                            ->required()
                            ->maxSize(BlogSettings::maxImageMb() * 1024)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'])
                            ->helperText('Up to '.BlogSettings::maxImageMb().' MB each. '
                                .strtoupper(implode(', ', BlogSettings::allowedExtensions()))
                                .' only — SVG is not accepted, because it can carry script.'),
                        TextInput::make('alt')
                            ->label('Alt text')
                            ->maxLength(255)
                            ->helperText('Optional, applied to everything in this upload. Describes the image for screen readers and search engines.'),
                    ])
                    ->action(fn (array $data) => $this->storeUploads($data)),
            ])
            ->recordActions([
                Action::make('setCover')
                    ->label('Set as cover')
                    ->icon('heroicon-o-photo')
                    ->requiresConfirmation()
                    ->modalDescription('This image becomes the article\'s cover. It stays in the media list either way.')
                    ->action(function (ArticleMedia $record): void {
                        /** @var Article $article */
                        $article = $this->getOwnerRecord();
                        $article->update([
                            'cover_disk' => $record->disk,
                            'cover_path' => $record->path,
                            'cover_alt' => $record->alt ?: $article->cover_alt,
                        ]);

                        Notification::make()->title('Cover updated')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No images yet')
            ->emptyStateDescription('Upload an image to get a URL you can paste into the article.');
    }

    /** Write each upload through ArticleStorage and record a row for it. */
    private function storeUploads(array $data): void
    {
        $storage = app(ArticleStorage::class);
        $article = $this->getOwnerRecord();
        $alt = trim((string) ($data['alt'] ?? '')) ?: null;

        $stored = 0;
        $rejected = 0;

        foreach ((array) ($data['files'] ?? []) as $file) {
            try {
                $article->media()->create($storage->put($file) + ['alt' => $alt]);
                $stored++;
            } catch (Throwable) {
                $rejected++;
            }
        }

        if ($stored > 0) {
            Notification::make()
                ->title($stored.' '.str('image')->plural($stored).' uploaded')
                ->success()
                ->send();
        }

        if ($rejected > 0) {
            Notification::make()
                ->title($rejected.' '.str('file')->plural($rejected).' rejected')
                ->body('Only '.implode(', ', BlogSettings::allowedExtensions()).' images are accepted.')
                ->danger()
                ->send();
        }
    }
}
