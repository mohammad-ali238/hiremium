<?php

namespace App\Filament\Admin\Resources\TopUpPacks;

use App\Filament\Admin\Resources\TopUpPacks\Pages\CreateTopUpPack;
use App\Filament\Admin\Resources\TopUpPacks\Pages\EditTopUpPack;
use App\Filament\Admin\Resources\TopUpPacks\Pages\ListTopUpPacks;
use App\Filament\Admin\Resources\TopUpPacks\Schemas\TopUpPackForm;
use App\Filament\Admin\Resources\TopUpPacks\Tables\TopUpPacksTable;
use App\Models\TopUpPack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TopUpPackResource extends Resource
{
    protected static ?string $model = TopUpPack::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?int $navigationSort = 55;

    public static function form(Schema $schema): Schema
    {
        return TopUpPackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TopUpPacksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTopUpPacks::route('/'),
            'create' => CreateTopUpPack::route('/create'),
            'edit' => EditTopUpPack::route('/{record}/edit'),
        ];
    }
}
