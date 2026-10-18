<?php

namespace App\Filament\Superadmin\Pages;

use Filament\Pages\Page;

class FinanceDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static \UnitEnum|string|null $navigationGroup = 'Moliya';
    protected static ?string $navigationLabel = 'Moliya Dashboard';
    protected static ?string $title = 'Moliya Dashboard';
    protected string $view = 'filament.superadmin.pages.finance-dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Superadmin\Widgets\FinanceStats::class,
            \App\Filament\Superadmin\Widgets\CommissionRevenueChart::class,
            \App\Filament\Superadmin\Widgets\TopRestaurantsTableWidget::class,
        ];
    }
}
