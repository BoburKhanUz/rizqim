<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use App\Models\Order;
use App\Enums\OrderStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ManagerOrdersOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->role === \App\Enums\UserRole::MANAGER;
    }

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return [];
        }

        $today = \Carbon\Carbon::today();

        $todaysOrders = Order::where('store_id', $tenant->id)
            ->whereDate('created_at', $today);

        $totalCount = (clone $todaysOrders)->count();
        $deliveredCount = (clone $todaysOrders)->where('status', OrderStatus::DELIVERED->value)->count();
        $pendingCount = (clone $todaysOrders)->where('status', OrderStatus::NEW->value)->count();

        return [
            Stat::make('Bugungi Buyurtmalar', $totalCount)
                ->description('Jami qabul qilingan')
                ->descriptionIcon('heroicon-m-inbox')
                ->color('info'),

            Stat::make('Yetkazib berilgan', $deliveredCount)
                ->description('Muvaffaqiyatli yakunlangan')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Kutilayotgan', $pendingCount)
                ->description('Hali tayyorlanmagan/yetkazilmagan')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
