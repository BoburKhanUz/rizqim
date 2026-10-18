<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Order;
use App\Models\Store;
use App\Enums\OrderStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GlobalGmvWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $deliveredOrders = Order::where('status', OrderStatus::DELIVERED->value);
        $totalGmv = $deliveredOrders->sum('total_price');
        $totalCommission = $deliveredOrders->sum('commission_amount');
        
        $activeStores = Store::where('is_active', true)->count();
        $totalOrdersCount = Order::count();

        return [
            Stat::make('Platform GMV', number_format($totalGmv, 0, ',', ' ') . ' UZS')
                ->description('Barcha muvaffaqiyatli buyurtmalar hajmi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Platform Revenue', number_format($totalCommission, 0, ',', ' ') . ' UZS')
                ->description('Sof komissiya foydasi')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Active Stores', $activeStores)
                ->description('Faoliyat yuritayotgan restoranlar')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),
                
            Stat::make('Total Orders', $totalOrdersCount)
                ->description('Tizimdagi jami buyurtmalar')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('gray'),
        ];
    }
}
