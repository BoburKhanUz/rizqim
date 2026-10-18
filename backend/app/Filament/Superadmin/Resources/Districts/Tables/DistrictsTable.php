<?php

namespace App\Filament\Superadmin\Resources\Districts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistrictsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('region.name_uz')
                    ->label('Region')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_uz')
                    ->searchable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
