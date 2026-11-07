<?php

namespace App\Filament\Admin\Resources\Articles\Tables;

use App\Models\Article;
use App\Support\BlogSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60)
                    ->description(fn (Article $record): string => '/blog/'.$record->slug),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === Article::STATUS_PUBLISHED ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                IconColumn::make('content_mode')
                    ->label('Mode')
                    ->icon(fn (string $state): string => $state === Article::MODE_HTML
                        ? 'heroicon-o-code-bracket'
                        : 'heroicon-o-pencil-square')
                    ->tooltip(fn (string $state): string => $state === Article::MODE_HTML ? 'HTML' : 'Rich editor'),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    // A future date is scheduled, not live — say so rather than
                    // letting it read as published.
                    ->badge()
                    ->color(fn (?Article $record): string => $record?->isPublished() ? 'success' : 'warning')
                    ->tooltip(fn (?Article $record): ?string => $record && $record->published_at?->isFuture()
                        ? 'Scheduled — appears automatically at this time'
                        : null)
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('Author')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->state(fn (Article $record): ?string => $record->trashed() ? 'Deleted' : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Article::STATUS_DRAFT => 'Draft',
                        Article::STATUS_PUBLISHED => 'Published',
                    ]),
                SelectFilter::make('article_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Opens the real public page. A draft or scheduled article
                // renders there for an admin only, so this doubles as preview.
                Action::make('view')
                    ->label(fn (Article $record): string => $record->isPublished() ? 'View' : 'Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Article $record): string => route('blog.show', $record->slug), shouldOpenInNewTab: true)
                    // A deleted article 404s for everyone, admins included.
                    ->visible(fn (Article $record): bool => BlogSettings::enabled() && ! $record->trashed()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
