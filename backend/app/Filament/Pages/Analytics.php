<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\AnalyticsStatsWidget;
use App\Filament\Widgets\AnalyticsHeroChart;

class Analytics extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected string $view = 'filament.pages.ui-page';
    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, [
            \App\Enums\UserRole::OWNER,
            \App\Enums\UserRole::SUPER_ADMIN,
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsHeroChart::class,
            AnalyticsStatsWidget::class,
        ];
    }
}
