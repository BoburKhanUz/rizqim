<?php

namespace App\Domain\Pos\Banquet;

use App\Models\BanquetEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * P6-51: an event was created, changed (moved, guests, package, extras), frozen, unfrozen or changed state. Dispatched
 * after the commit; `totalChanged` tells the payment schedule (P6-53) to re-plan its unpaid rows. Not broadcast.
 */
class BanquetEventChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly BanquetEvent $event,
        public readonly string $action,
        public readonly bool $totalChanged = false,
    ) {}
}
