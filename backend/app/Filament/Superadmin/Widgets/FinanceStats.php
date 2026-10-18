<?php

namespace App\Filament\Superadmin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Store;
use App\Models\Order;
use App\Enums\OrderStatus;

class FinanceStats extends BaseWidget
{
    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $deliveredOrders = Order::where('status', OrderStatus::DELIVERED->value);
        $totalGmv = $deliveredOrders->sum('total_price');
        $totalCommission = $deliveredOrders->sum('commission_amount');

        $totalDebt = Store::where('balance', '<', 0)->sum('balance');
        $totalPendingPayouts = Store::where('wallet_balance', '>', 0)->sum('wallet_balance');

        return [
            Stat::make('Platforma GMV (Jami Savdo)', number_format($totalGmv, 0, ',', ' ') . ' UZS')
                ->description('Barcha muvaffaqiyatli buyurtmalar hajmi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Platforma Sof Daromadi (Komissiya)', number_format($totalCommission, 0, ',', ' ') . ' UZS')
                ->description('Sof komissiya tushumlari')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Restoranlar Qarzdorligi (Bizga)', number_format(abs($totalDebt), 0, ',', ' ') . ' UZS')
                ->description('Restoranlarning platformaga komissiya qarzi')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('Kutilayotgan To\'lovlar (Bizdan)', number_format($totalPendingPayouts, 0, ',', ' ') . ' UZS')
                ->description('Restoranlar yechib olishi mumkin bo\'lgan mablag\'')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
        ];
    }
}
