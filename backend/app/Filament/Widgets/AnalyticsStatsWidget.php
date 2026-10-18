<?php

namespace App\Filament\Widgets;

use App\Filament\Support\StorePanelScope;
use App\Services\Ops\InventoryVelocityService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // 1. Orders Today (the tenant store's orders only)
        $ordersToday = StorePanelScope::orders()->whereDate('created_at', today())->count();
        $ordersYesterday = StorePanelScope::orders()->whereDate('created_at', today()->subDay())->count();

        $change = $ordersYesterday > 0 
            ? round((($ordersToday - $ordersYesterday) / $ordersYesterday) * 100)
            : 0;

        if ($change > 0) {
            $insightVal = "+{$change}% growth";
            $insightDesc = 'Increase compared to yesterday';
            $insightColor = 'success';
            $insightIcon = 'heroicon-m-arrow-trending-up';
        } elseif ($change < 0) {
            $insightVal = "{$change}% decline";
            $insightDesc = 'Decline compared to yesterday';
            $insightColor = 'danger';
            $insightIcon = 'heroicon-m-arrow-trending-down';
        } else {
            $insightVal = 'Steady volume';
            $insightDesc = 'Matches yesterday volume';
            $insightColor = 'gray';
            $insightIcon = 'heroicon-m-minus';
        }

        // 2. Top Selling Product (Today) using InventoryVelocityService
        $top = InventoryVelocityService::getTopSellingProductToday();

        // 3. Dynamic hourly chart data
        $hourlyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $hourlyData[] = StorePanelScope::orders()->whereDate('created_at', today())
                ->where('created_at', '>=', now()->subHours($i + 1))
                ->where('created_at', '<', now()->subHours($i))
                ->count();
        }

        return [
            Stat::make('Performance Insight', $insightVal)
                ->description($insightDesc)
                ->descriptionIcon($insightIcon)
                ->color($insightColor)
                ->chart($hourlyData),
                
            Stat::make('Orders Today', "{$ordersToday} Orders")
                ->description('Total checks processed today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Top Selling', $top['name'])
                ->description($top['desc'])
                ->descriptionIcon('heroicon-m-star')
                ->color($top['qty'] > 0 ? 'success' : 'gray'),
        ];
    }
}
