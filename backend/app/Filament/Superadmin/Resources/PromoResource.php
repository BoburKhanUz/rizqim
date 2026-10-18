<?php

namespace App\Filament\Superadmin\Resources;

use App\Models\Promo;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Filament\Superadmin\Resources\PromoResource\Pages;

class PromoResource extends Resource
{
    protected static ?string $model = Promo::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Aksiyalar / Bannerlar';
    protected static ?string $modelLabel = 'Aksiya / Banner';
    protected static ?string $pluralModelLabel = 'Aksiyalar / Bannerlar';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aksiya Tafsilotlari')
                    ->schema([
                        TextInput::make('title')
                            ->label('Sarlavha')
                            ->required()
                            ->maxLength(255)
                            ->placeholder("Masalan: Barcha shashliklarga 20% chegirma!"),

                        FileUpload::make('image')
                            ->label('Banner rasmi')
                            ->image()
                            ->directory('promos')
                            ->required(),

                        Select::make('store_id')
                            ->label('Birlashtirilgan Oshxona (ixtiyoriy)')
                            ->relationship('store', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('product_id', null))
                            ->placeholder("Tanlang..."),

                        Select::make('product_id')
                            ->label('Birlashtirilgan Mahsulot (ixtiyoriy)')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (callable $get) {
                                $storeId = $get('store_id');
                                if ($storeId) {
                                    return \App\Models\Product::where('store_id', $storeId)->pluck('name', 'id');
                                }
                                return \App\Models\Product::pluck('name', 'id');
                            })
                            ->placeholder("Tanlang..."),

                        TextInput::make('sort_order')
                            ->label('Tartib raqami')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Faol holatda')
                            ->default(true)
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Rasm')
                    ->circular(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Sarlavha')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Oshxona')
                    ->placeholder('Restoranga biriktirilmagan'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Mahsulot')
                    ->placeholder('Mahsulotga biriktirilmagan'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Tartib')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromos::route('/'),
        ];
    }
}
