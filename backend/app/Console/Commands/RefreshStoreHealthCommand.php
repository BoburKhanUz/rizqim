<?php

namespace App\Console\Commands;

use App\Services\Admin\Stores\StoreHealthCalculator;
use Illuminate\Console\Command;

/**
 * P4-22: recompute the stores' health now (the scheduler does it hourly: RefreshStoreHealthJob).
 */
class RefreshStoreHealthCommand extends Command
{
    protected $signature = 'admin:store-health {--store=* : only these store ids}';

    protected $description = "Ovqatxonalarning sog'liq bahosini qayta hisoblaydi (store_health_scores)";

    public function handle(StoreHealthCalculator $health): int
    {
        $ids = array_values(array_map('intval', (array) $this->option('store')));
        $written = $health->refresh($ids !== [] ? $ids : null);
        $this->info("{$written} ta ovqatxona hisoblandi.");

        return self::SUCCESS;
    }
}
