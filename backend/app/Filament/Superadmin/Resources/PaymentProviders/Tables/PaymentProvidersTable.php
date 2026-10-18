<?php

namespace App\Filament\Superadmin\Resources\PaymentProviders\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;

class PaymentProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')
                    ->label('Logo')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Kodi')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Holati'),
                TextColumn::make('updated_at')
                    ->label('Yangilangan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
