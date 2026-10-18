<?php

namespace App\Filament\Superadmin\Resources\Categories;

use App\Filament\Superadmin\Resources\Categories\Pages\CreateCategory;
use App\Filament\Superadmin\Resources\Categories\Pages\EditCategory;
use App\Filament\Superadmin\Resources\Categories\Pages\ListCategories;
use App\Filament\Superadmin\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Superadmin\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    
    // Categories are global in the marketplace, so they are not scoped to a specific store tenant
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Kategoriya';
    protected static ?string $pluralModelLabel = 'Kategoriyalar';
    protected static string|\UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = 2;
    
    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
