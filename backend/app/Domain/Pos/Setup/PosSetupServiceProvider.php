<?php

namespace App\Domain\Pos\Setup;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use Illuminate\Support\ServiceProvider;

/**
 * P3-13: binds the KitchenStationResolver contract to the branch's routing (KitchenStationRouter), one router per
 * request/job (it keeps a branch's routes in memory after the first read), and registers the halls/tables and shift
 * sections of GET /pos/bootstrap.
 */
class PosSetupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(KitchenStationRouter::class);
        $this->app->scoped(KitchenStationResolver::class, fn ($app) => $app->make(KitchenStationRouter::class));

        // The sections other modules own the data of (P3-12 halls and tables, P3-18 shifts)
        $this->app->tag([HallsBootstrapSection::class, ShiftBootstrapSection::class, QuickItemsBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }
}
