<?php

namespace App\Filament\Widgets;

use App\Services\Ops\KitchenLoadService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $kitchen = KitchenLoadService::getKitchenState();
        $courier = KitchenLoadService::getCourierPressure();

        $chefsCount = $kitchen->getOnlineChefsNeeded();
        $staffDesc = $kitchen->getActivePrepCount() > 5 ? 'Handling Peak spike load' : 'Managing steady pace';
        $staffColor = $kitchen->getActivePrepCount() > 5 ? 'warning' : 'success';

        return [
            Stat::make('Kitchen Heat State', $kitchen->isCritical() ? 'CRITICAL LOAD' : ($kitchen->isHigh() ? 'HIGH LOAD' : 'OPTIMAL LOAD'))
                ->description($kitchen->getDescription())
                ->color($kitchen->getColor())
                ->descriptionIcon('heroicon-m-fire'),
                
            Stat::make('Kitchen Staff Online', "{$chefsCount} Active")
                ->description($staffDesc)
                ->color($staffColor)
                ->descriptionIcon('heroicon-m-user-group'),
                
            Stat::make('Delivery Active', $courier->getActiveTransitCount() . ' En Route')
                ->description($courier->getDescription())
                ->color($courier->getColor())
                ->descriptionIcon('heroicon-m-truck'),
        ];
    }
}
