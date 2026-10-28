<?php

namespace App\Providers;

use App\Jobs\RefreshPlatformStatsJob;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Today\Attention\FailedFiscalReceiptsSource;
use App\Services\Admin\Today\Attention\OfflineTerminalsSource;
use App\Services\Admin\Today\Attention\OverdueInvoicesSource;
use App\Services\Admin\Today\Attention\PendingPayoutsSource;
use App\Services\Admin\Today\Attention\SettlementFailuresSource;
use App\Services\Admin\Today\Attention\StuckTopupsSource;
use App\Services\Admin\Today\PlatformStats;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P4-13: the Bugun section. Its "Diqqat talab qiladi" sources (tagged AttentionSource::TAG, collected by
 * App\Services\Admin\Attention\AttentionCollector) and the refresh of the aggregate tables behind GET /admin/today.
 */
class AdminTodayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            SettlementFailuresSource::class,
            FailedFiscalReceiptsSource::class,
            OfflineTerminalsSource::class,
            OverdueInvoicesSource::class,
            PendingPayoutsSource::class,
            StuckTopupsSource::class,
        ], AttentionSource::TAG);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Today and yesterday every 5 minutes; whatever a run has not covered yet is counted live by the page
            $schedule->job(new RefreshPlatformStatsJob(2))
                ->name('admin-platform-stats')
                ->everyFiveMinutes()
                ->withoutOverlapping(15);

            // The last 8 days once a night: later cancellations and refunds, and last week's day for the comparison
            $schedule->job(new RefreshPlatformStatsJob(8))
                ->name('admin-platform-stats-week')
                ->dailyAt('00:20')
                ->timezone(PlatformStats::TIMEZONE)
                ->withoutOverlapping(60);
        });
    }
}
