<?php

namespace App\Filament\Widgets;

use App\Services\Ops\InventoryVelocityService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $top = InventoryVelocityService::getTopSellingProductToday();
        $lowStockCount = InventoryVelocityService::getLowStockAlertsCount();
        $lowStockNames = InventoryVelocityService::getLowStockNamesString();
        $outOfStockCount = InventoryVelocityService::getOutOfStockCount();

        $topSellingColor = $top['qty'] > 0 ? 'success' : 'gray';
        $lowStockColor = $lowStockCount > 0 ? 'warning' : 'success';
        $lowStockDesc = $lowStockCount > 0 ? "Critical: {$lowStockNames}" : 'All items healthy';
        $outOfStockColor = $outOfStockCount > 0 ? 'danger' : 'success';
        $outOfStockDesc = $outOfStockCount > 0 ? 'Urgent reorder required' : 'Kitchen fully operational';

        return [
            Stat::make('Top Selling (Today)', $top['name'])
                ->description($top['desc'])
                ->color($topSellingColor),
                
            Stat::make('Low Stock Alerts', "{$lowStockCount} items")
                ->description($lowStockDesc)
                ->color($lowStockColor),
                
            Stat::make('Out of Stock', "{$outOfStockCount} items")
                ->description($outOfStockDesc)
                ->color($outOfStockColor),
        ];
    }
}
