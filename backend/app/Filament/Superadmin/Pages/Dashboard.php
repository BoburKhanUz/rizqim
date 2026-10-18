<?php

namespace App\Filament\Superadmin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Superadmin\Widgets\PlatformOverviewWidget::class,
            \App\Filament\Superadmin\Widgets\PlatformOrdersChart::class,
        ];
    }
}
