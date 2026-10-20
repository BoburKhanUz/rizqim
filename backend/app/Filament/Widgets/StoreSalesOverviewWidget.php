<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use App\Models\Order;
use App\Enums\OrderStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreSalesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->role === \App\Enums\UserRole::OWNER;
    }

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return [];
        }

        $today = \Carbon\Carbon::today();

        $todaysDeliveredOrders = Order::where('store_id', $tenant->getKey())
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereDate('created_at', $today);

        $todaysRevenue = $todaysDeliveredOrders->sum('total_price');
        $todaysOrders = $todaysDeliveredOrders->count();
        $avgOrderValue = $todaysOrders > 0 ? $todaysRevenue / $todaysOrders : 0;

        return [
            Stat::make('Today\'s Revenue', number_format($todaysRevenue, 0, ',', ' ') . ' UZS')
                ->description('Bugungi jami sotuvlar')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Today\'s Orders', $todaysOrders)
                ->description('Bugungi muvaffaqiyatli buyurtmalar')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Average Order Value', number_format($avgOrderValue, 0, ',', ' ') . ' UZS')
                ->description('Bugungi o\'rtacha chek')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }
}
