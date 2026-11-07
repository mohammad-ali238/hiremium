<?php

namespace App\Filament\Admin\Resources\Articles\Pages;

use App\Filament\Admin\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Support\BlogSettings;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The public page, in a new tab. Drafts and scheduled articles
            // render there for admins only, so this is also the preview.
            Action::make('view')
                ->label(fn (): string => $this->record instanceof Article && $this->record->isPublished() ? 'View' : 'Preview')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => route('blog.show', $this->record->slug), shouldOpenInNewTab: true)
                ->visible(fn (): bool => BlogSettings::enabled()
                    && $this->record instanceof Article
                    && ! $this->record->trashed()),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
