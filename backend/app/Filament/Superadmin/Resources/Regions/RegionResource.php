<?php

namespace App\Filament\Superadmin\Resources\Regions;

use App\Filament\Superadmin\Resources\Regions\Pages\CreateRegion;
use App\Filament\Superadmin\Resources\Regions\Pages\EditRegion;
use App\Filament\Superadmin\Resources\Regions\Pages\ListRegions;
use App\Filament\Superadmin\Resources\Regions\Schemas\RegionForm;
use App\Filament\Superadmin\Resources\Regions\Tables\RegionsTable;
use App\Models\Region;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'Sozlamalar';
    protected static ?string $navigationLabel = 'Viloyatlar';
    protected static ?string $modelLabel = 'Viloyat';
    protected static ?string $pluralModelLabel = 'Viloyatlar';

    protected static ?string $recordTitleAttribute = 'name_uz';

    public static function form(Schema $schema): Schema
    {
        return RegionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Superadmin\Resources\Regions\RelationManagers\DistrictsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegions::route('/'),
            'create' => CreateRegion::route('/create'),
            'edit' => EditRegion::route('/{record}/edit'),
        ];
    }
}
