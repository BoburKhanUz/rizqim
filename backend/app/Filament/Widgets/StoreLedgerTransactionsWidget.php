<?php

namespace App\Filament\Widgets;

use App\Models\StoreTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;

class StoreLedgerTransactionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Qarz Hisobi Tranzaksiyalari (Ledger)';
    protected static ?int $sort = 3;
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
                StoreTransaction::query()
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
                        'commission' => 'danger',
                        'topup' => 'success',
                        'subscription' => 'warning', // P1-23
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'commission' => 'KOMISSIYA',
                        'topup' => 'TO\'LDURILDI',
                        'subscription' => 'OBUNA', // P1-23
                        default => strtoupper($state instanceof \BackedEnum ? $state->value : $state),
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money('UZS', locale: 'uz-UZ')
                    ->color(fn ($state) => $state < 0 ? 'success' : 'danger')
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
            ->emptyStateHeading('Qarz tarixi mavjud emas')
            ->emptyStateDescription('Balans bilan bog\'liq operatsiyalar bu yerda ko\'rsatiladi.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
