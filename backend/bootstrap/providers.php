<?php

return [
    App\Providers\AppServiceProvider::class,
    // P1-12: device guard, permissions Gate, audit observers, auth/device rate limits
    App\Providers\AccessServiceProvider::class,
    // OpenAPI docs (/docs/api) and the api-spec:export command (P1-11)
    App\Providers\ApiDocsServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\SuperadminPanelProvider::class,
];
