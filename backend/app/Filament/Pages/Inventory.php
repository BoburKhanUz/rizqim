<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\InventoryStatsWidget;
use App\Filament\Widgets\InventoryLowStockWidget;

class Inventory extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected string $view = 'filament.pages.ui-page';
    protected static ?int $navigationSort = 3;

    protected function getHeaderWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
            InventoryLowStockWidget::class,
        ];
    }
}
