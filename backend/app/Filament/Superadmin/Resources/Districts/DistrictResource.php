<?php

namespace App\Filament\Superadmin\Resources\Districts;

use App\Filament\Superadmin\Resources\Districts\Pages\CreateDistrict;
use App\Filament\Superadmin\Resources\Districts\Pages\EditDistrict;
use App\Filament\Superadmin\Resources\Districts\Pages\ListDistricts;
use App\Filament\Superadmin\Resources\Districts\Schemas\DistrictForm;
use App\Filament\Superadmin\Resources\Districts\Tables\DistrictsTable;
use App\Models\District;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Sozlamalar';
    protected static ?string $navigationLabel = 'Tumanlar';
    protected static ?string $modelLabel = 'Tuman';
    protected static ?string $pluralModelLabel = 'Tumanlar';
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name_uz';

    public static function form(Schema $schema): Schema
    {
        return DistrictForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DistrictsTable::configure($table);
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
            'index' => ListDistricts::route('/'),
            'create' => CreateDistrict::route('/create'),
            'edit' => EditDistrict::route('/{record}/edit'),
        ];
    }
}
