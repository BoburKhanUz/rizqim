<?php

return [
    App\Providers\AppServiceProvider::class,
    // P1-12: device guard, permissions Gate, audit observers, auth/device rate limits
    App\Providers\AccessServiceProvider::class,
    // OpenAPI docs (/docs/api) and the api-spec:export command (P1-11)
    App\Providers\ApiDocsServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\SuperadminPanelProvider::class,
    // P3-13: kitchen station routing (KitchenStationResolver)
    App\Domain\Pos\Setup\PosSetupServiceProvider::class,
    // P3-11: fiscal receipts (FiscalIssuer, FiscalLineBuilder, FiscalReadiness)
    App\Providers\FiscalServiceProvider::class,
    // P3-17: bookings, deposits, customer accounts (AdvanceLedger, CustomerAccountLedger)
    App\Providers\PosBookingServiceProvider::class,
    // P3-12: POS hall orders (PosOrderOpener), halls and tables
    App\Providers\PosOrdersServiceProvider::class,
    // P3-18: bills, payments, shifts, refunds, manager approvals (ManagerApprovals)
    App\Domain\Pos\Billing\PosBillingServiceProvider::class,
];
