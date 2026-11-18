<?php

namespace App\Providers;

use App\Domain\Integrations\IntegrationEvent;
use App\Support\Partitions\PartitionArchiver;
use App\Support\Partitions\PartitionConverter;
use App\Support\Partitions\PartitionManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md sections 2-5): the journal partitions' schedules (times are UTC; Tashkent is +5).
 * - db:partitions:ensure 00:40 (05:40 Tashkent, before trade): the next months, DEFAULT emptied;
 * - db:partitions:archive 22:40 (03:40 Tashkent, the quietest hour): old months detached / verified copies dropped;
 * - integration_events pruning (its model lives outside app/Models, which the plain `model:prune` scans).
 */
class PartitionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PartitionManager::class);
        $this->app->singleton(PartitionConverter::class);
        $this->app->singleton(PartitionArchiver::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('db:partitions:ensure')->dailyAt('00:40')->timezone('UTC')->withoutOverlapping(30)->name('partitions-ensure');
            $schedule->command('db:partitions:archive')->dailyAt('22:40')->timezone('UTC')->withoutOverlapping(120)->name('partitions-archive');
            $schedule->command('model:prune', ['--model' => [IntegrationEvent::class]])->dailyAt('01:10')->timezone('UTC')->withoutOverlapping(60)->name('prune-integration-events');
        });
    }
}
