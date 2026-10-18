<?php

namespace App\Filament\Widgets;

use App\Services\Ops\DelayDetectionService;
use App\Services\Ops\KitchenLoadService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarRoomUrgentZone extends BaseWidget
{
    protected static ?int $sort = -10;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // 1. Delayed / Late Orders
        $totalDelayed = DelayDetectionService::getDelayedOrdersCount();
        $isPeak = DelayDetectionService::isPeakHour();
        
        $delayedColor = 'success';
        $delayedDesc = 'All orders are within time limits';
        $delayedIcon = 'heroicon-m-check-circle';
        
        if ($totalDelayed > 5) {
            $delayedColor = 'danger';
            $delayedDesc = $isPeak 
                ? 'CRITICAL: Rush peak delays. Kitchen backlog.'
                : 'CRITICAL: Off-peak delay bottleneck.';
            $delayedIcon = 'heroicon-m-exclamation-triangle';
        } elseif ($totalDelayed > 0) {
            $delayedColor = 'warning';
            $delayedDesc = "Alert: {$totalDelayed} orders exceeding target prep time";
            $delayedIcon = 'heroicon-m-clock';
        }

        // 2. Courier Capacity & Dispatch
        $courier = KitchenLoadService::getCourierPressure();

        // 3. Kitchen Heat Load Map
        $kitchen = KitchenLoadService::getKitchenState();

        return [
            Stat::make('Delayed Orders', $totalDelayed . ' LATE')
                ->description($delayedDesc)
                ->descriptionIcon($delayedIcon)
                ->color($delayedColor),
                
            Stat::make('Couriers Standby', $courier->getActiveTransitCount() . ' EN ROUTE')
                ->description($courier->getDescription())
                ->descriptionIcon('heroicon-m-truck')
                ->color($courier->getColor()),
                
            Stat::make('Kitchen Heat Load', $kitchen->getMap())
                ->description($kitchen->getDescription())
                ->descriptionIcon('heroicon-m-fire')
                ->color($kitchen->getColor()),
        ];
    }
}
