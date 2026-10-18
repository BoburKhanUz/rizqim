<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\WarRoomUrgentZone;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\LiveActivityFeed;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TopProductsChart;

use App\Filament\Widgets\StoreBalanceWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            StoreBalanceWidget::class,
            WarRoomUrgentZone::class,
            StatsOverview::class,
            OrdersChart::class,
            LiveActivityFeed::class,
            TopProductsChart::class,
        ];
    }
}
