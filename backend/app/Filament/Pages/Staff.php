<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\StaffStatsWidget;
use App\Filament\Widgets\StaffPresenceWidget;

class Staff extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected string $view = 'filament.pages.ui-page';
    protected static ?int $navigationSort = 5;

    protected function getHeaderWidgets(): array
    {
        return [
            StaffStatsWidget::class,
            StaffPresenceWidget::class,
        ];
    }
}
