<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Pos\Setup\PosBootstrap;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P6-34 "Qadoq, 24/7, olib ketish vaqti": the bootstrap's `takeaway` section and the minute job that sends the
 * orders for a later pickup to the kitchen at their kitchen time (pos:fire-scheduled-pickups).
 */
class PosTakeawayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([TakeawayBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([FireScheduledPickupsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pos:fire-scheduled-pickups')->everyMinute()->withoutOverlapping(5)->name('pos-fire-scheduled-pickups');
        });
    }
}
