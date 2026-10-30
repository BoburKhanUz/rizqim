<?php

namespace App\Domain\Pos\Takeaway;

use Illuminate\Console\Command;

/**
 * P6-34: every minute, the orders for a later pickup whose kitchen time has come go to the kitchen (ScheduledPickup).
 */
class FireScheduledPickupsCommand extends Command
{
    protected $signature = 'pos:fire-scheduled-pickups';

    protected $description = 'Send the orders for a later pickup to the kitchen at their kitchen time (pickup time - prep time)';

    public function handle(ScheduledPickup $pickup): int
    {
        $this->info($pickup->fireDue() . ' scheduled pickup order(s) sent to the kitchen');

        return self::SUCCESS;
    }
}
