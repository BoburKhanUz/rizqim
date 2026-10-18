<?php

namespace App\Filament\Widgets;

use App\Services\Ops\KitchenLoadService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffPresenceWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $kitchen = KitchenLoadService::getKitchenState();
        $courier = KitchenLoadService::getCourierPressure();
        $operator = KitchenLoadService::getOperatorStateMap();

        return [
            Stat::make('Kitchen Capacity', $kitchen->getMap())
                ->description($kitchen->getDescription())
                ->descriptionIcon('heroicon-m-fire')
                ->color($kitchen->getColor()),

            Stat::make('Courier Capacity', $courier->getMap())
                ->description($courier->getDescription())
                ->descriptionIcon('heroicon-m-truck')
                ->color($courier->getColor()),

            Stat::make('Control Desk Capacity', $operator['map'])
                ->description($operator['desc'])
                ->descriptionIcon('heroicon-m-user-group')
                ->color($operator['color']),
        ];
    }
}
