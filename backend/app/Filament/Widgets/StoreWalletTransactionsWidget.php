<?php

namespace App\Filament\Widgets;

use App\Models\StoreWalletTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;

class StoreWalletTransactionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Hamyon Tranzaksiyalari (Mijoz to\'lovlari va yechishlar)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, [
            \App\Enums\UserRole::OWNER,
            \App\Enums\UserRole::SUPER_ADMIN,
        ]);
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(
                StoreWalletTransaction::query()
                    ->where('store_id', $tenant?->getKey())
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sana/Vaqt')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'settlement' => 'success',
                        'payout' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'settlement' => 'TUSHUM (ONLINE)',
                        'payout' => 'YECHIB OLINDI',
                        default => strtoupper($state instanceof \BackedEnum ? $state->value : $state),
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money('UZS', locale: 'uz-UZ')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => ($state < 0 ? '-' : '+') . number_format(abs($state)) . ' UZS')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Oldingi Balans')
                    ->money('UZS', locale: 'uz-UZ')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Yangi Balans')
                    ->money('UZS', locale: 'uz-UZ')
                    ->weight('bold')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Izoh')
                    ->placeholder('Izohsiz'),
            ])
            ->emptyStateHeading('Hamyon tarixi mavjud emas')
            ->emptyStateDescription('Mijozlarning onlayn to\'lovlari va siz tomondan pul yechish operatsiyalari bu yerda ko\'rsatiladi.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }
}
