<?php

namespace App\Jobs;

use App\Services\Admin\Stores\StoreHealthCalculator;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * P4-22: recomputes every store's health (store_health_scores) for the Ovqatxonalar list, the at-risk filter and the
 * "Ketish xavfi" attention rows. Scheduled hourly (AdminStoresServiceProvider); not queued: it runs inside the
 * scheduler. Safe to run again at any time (each run overwrites the rows).
 */
class RefreshStoreHealthJob
{
    use Dispatchable;

    public function handle(StoreHealthCalculator $health): void
    {
        $health->refresh();
    }
}
