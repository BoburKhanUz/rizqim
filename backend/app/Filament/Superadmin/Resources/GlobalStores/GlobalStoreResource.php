<?php

namespace App\Filament\Superadmin\Resources\GlobalStores;

use App\Filament\Superadmin\Resources\GlobalStores\Pages\CreateGlobalStore;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\EditGlobalStore;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\ListGlobalStores;
use App\Filament\Superadmin\Resources\GlobalStores\Schemas\GlobalStoreForm;
use App\Filament\Superadmin\Resources\GlobalStores\Tables\GlobalStoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GlobalStoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|\UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Restoran';
    protected static ?string $pluralModelLabel = 'Restoranlar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GlobalStoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GlobalStoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Superadmin\Resources\GlobalStores\RelationManagers\BranchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalStores::route('/'),
            'create' => CreateGlobalStore::route('/create'),
            'edit' => EditGlobalStore::route('/{record}/edit'),
        ];
    }
}
