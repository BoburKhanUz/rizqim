<?php

namespace App\Providers;

use App\Jobs\RefreshStoreHealthJob;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Stores\ApplicationPipeline;
use App\Services\Admin\Stores\Attention\ChurnRiskStoresSource;
use App\Services\Admin\Stores\Attention\DueStoreTasksSource;
use App\Services\Admin\Stores\Attention\NewApplicationsSource;
use App\Services\Admin\Stores\Attention\OverdueApplicationsSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * P4-22: Boshqaruv markazi → Ovqatxonalar. Its "Diqqat talab qiladi" sources (new and overdue applications, stores
 * at churn risk, CRM tasks and contacts due), the hourly health refresh, the rate limits of the public application
 * form and of broadcasts, and the public route POST /api/v1/store-applications (routes/api_store_applications.php,
 * loaded here so routes/api.php stays untouched).
 */
class AdminStoresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            NewApplicationsSource::class,
            OverdueApplicationsSource::class,
            ChurnRiskStoresSource::class,
            DueStoreTasksSource::class,
        ], AttentionSource::TAG);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new RefreshStoreHealthJob())
                ->name('admin-store-health')
                ->hourlyAt(17)
                ->withoutOverlapping(50);
        });

        // The public form: 5 an hour and 20 a day per address, 3 a day per phone
        RateLimiter::for('store-applications', function (Request $request) {
            $phone = ApplicationPipeline::normalizePhone((string) $request->input('phone', '')) ?? 'none';

            return [
                Limit::perHour(5)->by('store-app:ip:' . $request->ip()),
                Limit::perDay(20)->by('store-app:ip-day:' . $request->ip()),
                Limit::perDay(3)->by('store-app:phone:' . $phone),
            ];
        });

        // Announcements to store owners: 5 attempts an hour per member (the daily cap is StoreBroadcastService's)
        RateLimiter::for('admin-store-broadcast', fn (Request $request) => Limit::perHour(5)->by('admin-broadcast:' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if (!$this->app->routesAreCached()) {
            Route::middleware('api')->prefix('api/v1')->group(base_path('routes/api_store_applications.php'));
        }
    }
}
