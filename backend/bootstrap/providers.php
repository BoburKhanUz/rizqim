<?php

return [
    App\Providers\AppServiceProvider::class,
    // P1-12: device guard, permissions Gate, audit observers, auth/device rate limits
    App\Providers\AccessServiceProvider::class,
    // OpenAPI docs (/docs/api) and the api-spec:export command (P1-11)
    App\Providers\ApiDocsServiceProvider::class,
    // P3-13: kitchen station routing (KitchenStationResolver)
    App\Domain\Pos\Setup\PosSetupServiceProvider::class,
    // P6-15: the daily menu, next batches and kompleks sections of GET /pos/bootstrap
    App\Domain\Pos\Menu\PosMenuServiceProvider::class,
    // P3-11: fiscal receipts (FiscalIssuer, FiscalLineBuilder, FiscalReadiness)
    App\Providers\FiscalServiceProvider::class,
    // P3-17: bookings, deposits, customer accounts (AdvanceLedger, CustomerAccountLedger)
    App\Providers\PosBookingServiceProvider::class,
    // P3-12: POS hall orders (PosOrderOpener), halls and tables
    App\Providers\PosOrdersServiceProvider::class,
    // P3-18: bills, payments, shifts, refunds, manager approvals (ManagerApprovals)
    App\Domain\Pos\Billing\PosBillingServiceProvider::class,
    // P3-31: offline limited mode: POST /pos/sync, the bootstrap's offline section
    App\Domain\Pos\Sync\PosSyncServiceProvider::class,
    // P6-13: order numbers and calling (PickupTracker, the bootstrap's queue section)
    App\Domain\Pos\Orders\PosQueueServiceProvider::class,
    // P6-33: KDS v2: the expo (assembly) station the kitchen core asks (AssemblyTracker)
    App\Domain\Pos\Kitchen\KdsServiceProvider::class,
    // P6-23: staff pay (the tips source of the tips component, the bootstrap's staff_pay section)
    App\Domain\Pos\StaffPay\StaffPayServiceProvider::class,
    // P6-22: tips (choy puli): the bootstrap's tips section
    App\Domain\Pos\Tips\PosTipsServiceProvider::class,
    // P6-34: packaging, 24/7 and the pickup time: the bootstrap's takeaway section, the scheduled pickups' minute job
    App\Domain\Pos\Takeaway\PosTakeawayServiceProvider::class,
    // P4-15: Monitoring: open-alerts attention source, alert evaluator schedule
    App\Providers\AdminMonitoringServiceProvider::class,
    // P4-13: Bugun: attention sources (settlements, payouts, top-ups, invoices, terminals, fiscal), stats refresh
    App\Providers\AdminTodayServiceProvider::class,
    // P4-14: Buyurtmalar: order attention sources (unaccepted, late, no courier), platform.ops order events
    App\Providers\AdminOrdersServiceProvider::class,
    // P4-21: Billing v2: entitlements (plan features), plan.feature / billing.unlocked middleware, dunning schedule
    App\Providers\BillingServiceProvider::class,
    // P4-22: Ovqatxonalar: attention sources (applications, churn risk, CRM tasks), health refresh, public application form
    App\Providers\AdminStoresServiceProvider::class,
    // P4-23: Moliya: attention sources (payouts and adjustments waiting for a second approval)
    App\Providers\AdminFinanceServiceProvider::class,
    // P4-31: Ko'rsatkichlar and Excel exports: the export rate limit
    App\Providers\AdminMetricsServiceProvider::class,
    // P4-31: Mijozlar va yordam: the attention source of complaints waiting for the support team
    App\Providers\AdminCustomersServiceProvider::class,
    // P4-31: Tizim and Integratsiyalar: the attention source of orders an outside POS has not confirmed
    App\Providers\AdminSystemServiceProvider::class,
];
