<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PaymentsRecentWidget extends BaseWidget
{
    protected static ?string $heading = '💳 Transactional Activity Ticker';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->since()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('short_code')
                    ->label('Order Reference')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Gateway / Method')
                    ->badge()
                    ->color(fn ($state): string => match (strtolower($state)) {
                        'click' => 'success',
                        'payme' => 'info',
                        'cash' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Payment Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value ?? $state) {
                        'delivered' => 'success',
                        'canceled' => 'danger',
                        'new' => 'gray',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => match ($state->value ?? $state) {
                        'delivered' => 'COMPLETED',
                        'canceled' => 'FAILED / REJECT',
                        'new' => 'AWAITING FLOW',
                        default => 'IN PROGRESS',
                    }),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Amount')
                    ->money('UZS', locale: 'uz-UZ')
                    ->alignment('right'),
            ])
            ->emptyStateHeading('🛡️ Cashflow center active')
            ->emptyStateDescription('Your recent Payme, Click, and Cash activities will stream here in real-time as orders are processed. Failed gateways or rejected payments will escalate with highly visible warning alerts.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }
}
