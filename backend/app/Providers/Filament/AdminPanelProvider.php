<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->tenant(\App\Models\Store::class, slugAttribute: 'uuid')
            ->tenantProfile(\App\Filament\Pages\Tenancy\EditStoreProfile::class)
            ->colors([
                'primary' => Color::Indigo,
                'gray' => [
                    50 => '250, 250, 250',
                    100 => '244, 244, 245',
                    200 => '228, 228, 231',
                    300 => '212, 212, 216',
                    400 => '161, 161, 170',
                    500 => '113, 113, 122',
                    600 => '82, 82, 91',
                    700 => '63, 63, 70',
                    800 => '39, 39, 42',
                    900 => '24, 24, 27',
                    950 => '15, 15, 17',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\StoreBalanceWidget::class,
                \App\Filament\Widgets\StoreSalesOverviewWidget::class,
                \App\Filament\Widgets\DailyOrdersChart::class,
                \App\Filament\Widgets\CustomerRetentionWidget::class,
                \App\Filament\Widgets\ManagerOrdersOverviewWidget::class,
                \App\Filament\Widgets\StoreLedgerTransactionsWidget::class,
                \App\Filament\Widgets\StoreWalletTransactionsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('@include("filament.custom-css")')
        );
    }
}
