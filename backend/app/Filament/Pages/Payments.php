<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\PaymentsStatsWidget;
use App\Filament\Widgets\PaymentsRecentWidget;



class Payments extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected string $view = 'filament.pages.ui-page';
    protected static ?int $navigationSort = 4;

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
            PaymentsStatsWidget::class,
            PaymentsRecentWidget::class,
        ];
    }
}
