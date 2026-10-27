<?php

namespace App\Jobs;

use App\Services\Admin\Orders\OrderSlaWatcher;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * P4-14: records and broadcasts marketplace SLA crossings (ok → warning → late) once per order and stage
 * (OrderSlaWatcher). Not queued: it runs inside the scheduler, every minute.
 */
class WatchOrderSlaJob
{
    use Dispatchable;

    public function handle(OrderSlaWatcher $watcher): void
    {
        $watcher->run();
    }
}
