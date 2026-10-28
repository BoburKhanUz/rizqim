<?php

namespace App\Providers;

use App\Jobs\EvaluateAlertsJob;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Monitoring\OpenAlertsAttentionSource;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P4-15: Monitoring in the Boshqaruv markazi. Registers the open-alerts source of "Bugun → Diqqat talab qiladi" and
 * schedules the alert evaluator (every minute).
 */
class AdminMonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([OpenAlertsAttentionSource::class], AttentionSource::TAG);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new EvaluateAlertsJob)->everyMinute()->withoutOverlapping(5)->name('monitoring-evaluate-alerts');
        });
    }
}
