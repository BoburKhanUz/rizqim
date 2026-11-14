<?php

namespace App\Domain\Pos\Banquet;

use App\Domain\Pos\Banquet\Console\ReleaseExpiredHolds;
use App\Domain\Pos\Setup\PosBootstrap;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P6-51/P6-52: banquet events. The minute job releasing expired holds (pos:banquet-release-holds) and the `banquet`
 * section of GET /pos/bootstrap (BanquetBootstrapSection).
 */
class PosBanquetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([BanquetBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseExpiredHolds::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pos:banquet-release-holds')->everyMinute()->withoutOverlapping(5);
        });
    }
}
