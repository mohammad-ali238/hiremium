<?php

namespace App\Filament\Admin\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('credit_limit')->numeric()->sortable(),
                TextColumn::make('price')->money('USD')->sortable(),
                TextColumn::make('duration_days')->label('Days')->sortable(),
                IconColumn::make('is_active')->boolean(),
                IconColumn::make('is_public')->label('Public')->boolean(),
                TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')
                    ->label('Active subs'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
