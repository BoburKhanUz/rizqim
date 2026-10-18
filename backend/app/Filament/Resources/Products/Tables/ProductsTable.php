<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use App\Models\Product;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=Product'),
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn(Product $record): ?string => $record->sort_order === 1 ? '🔥 Bestseller' : null),
                TextColumn::make('category.name')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants'),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
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
