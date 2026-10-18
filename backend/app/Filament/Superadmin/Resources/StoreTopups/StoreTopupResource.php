<?php

namespace App\Filament\Superadmin\Resources\StoreTopups;

use App\Filament\Superadmin\Resources\StoreTopups\Pages\ListStoreTopups;
use App\Models\StoreTopupRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StoreTopupResource extends Resource
{
    protected static ?string $model = StoreTopupRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationLabel = 'Balans To\'ldirish Tarixi';
    protected static ?string $modelLabel = 'Balans To\'ldirish';
    protected static ?string $pluralModelLabel = 'Balans To\'ldirish Tarixi';
    protected static ?int $navigationSort = 5;
    protected static string|\UnitEnum|null $navigationGroup = 'Moliya';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaratilgan Vaqt')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Restoran')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money('UZS', locale: 'uz-UZ')
                    ->sortable()
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Tizim')
                    ->badge()
                    ->color(fn ($state): string => match (strtolower($state)) {
                        'click' => 'success',
                        'payme' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'completed' => 'Muvaffaqiyatli',
                        'pending' => 'Kutilmoqda',
                        'failed' => 'Xatolik',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('To\'langan Vaqt')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Kutilmoqda',
                        'completed' => 'Muvaffaqiyatli',
                        'failed' => 'Xatolik',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'click' => 'Click',
                        'payme' => 'Payme',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreTopups::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
