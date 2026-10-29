<?php

namespace App\Domain\Pos\Setup;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use Illuminate\Support\ServiceProvider;

/**
 * P3-13: binds the KitchenStationResolver contract to the branch's routing (KitchenStationRouter), one router per
 * request/job (it keeps a branch's routes in memory after the first read), and registers the halls/tables and shift
 * sections of GET /pos/bootstrap. P6-11: the branch's effective modules (PosModules, once per request like the
 * Entitlements it reads), the bootstrap's `format` and the `pos.module:<key>` route middleware.
 */
class PosSetupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(KitchenStationRouter::class);
        $this->app->scoped(KitchenStationResolver::class, fn ($app) => $app->make(KitchenStationRouter::class));

        // The sections other modules own the data of (P3-12 halls and tables, P3-18 shifts)
        $this->app->tag([HallsBootstrapSection::class, ShiftBootstrapSection::class, QuickItemsBootstrapSection::class], PosBootstrap::CONTRIBUTORS);

        // P6-11: format profile and modules
        $this->app->scoped(PosModules::class);
        $this->app->tag([PosFormatBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('pos.module', \App\Http\Middleware\EnsurePosModule::class);
    }
}
