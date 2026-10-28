<?php

namespace App\Providers;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Http\Middleware\EnsureBillingUnlocked;
use App\Http\Middleware\EnsurePlanFeature;
use App\Jobs\SendBillingRemindersJob;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Finance\Billing\EntitlementsBootstrapSection;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P4-21: Billing v2 (docs/BILLING.md). Entitlements once per request, the POS bootstrap's `entitlements` section, the
 * `plan.feature:<feature>` and `billing.unlocked` route middleware, and the daily dunning run (reminders and the lock).
 */
class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Entitlements::class);
        $this->app->tag([EntitlementsBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('plan.feature', EnsurePlanFeature::class);
        $this->app['router']->aliasMiddleware('billing.unlocked', EnsureBillingUnlocked::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new SendBillingRemindersJob)
                ->dailyAt((string) config('billing.dunning.run_at', '10:05'))
                ->timezone((string) config('billing.timezone', 'Asia/Tashkent'))
                ->withoutOverlapping(60)
                ->name('billing-dunning');
        });
    }
}
