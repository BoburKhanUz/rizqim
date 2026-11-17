<?php

namespace App\Providers;

use App\Jobs\EvaluateAlertsJob;
use App\Jobs\QueueHeartbeatJob;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Monitoring\OpenAlertsAttentionSource;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P4-15: Monitoring in the Boshqaruv markazi. Registers the open-alerts source of "Bugun → Diqqat talab qiladi" and
 * schedules the alert evaluator (every minute). M3: and the queue worker's heartbeat (QueueHeartbeatJob, every minute,
 * to the `default` queue; read by the queue_worker_stale rule).
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
            // A closure, so each minute's job carries the minute it was dispatched in (how long it waited is shown). A
            // block body: a returned PendingDispatch would be kept by the event and dispatched only when it is freed
            $schedule->call(function (): void {
                QueueHeartbeatJob::dispatch();
            })->everyMinute()->name('monitoring-queue-heartbeat');
        });
    }
}
