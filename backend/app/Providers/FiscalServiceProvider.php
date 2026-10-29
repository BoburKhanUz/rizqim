<?php

namespace App\Providers;

use App\Domain\Fiscal\Console\RetryPendingFiscalReceipts;
use App\Domain\Fiscal\FiscalDocumentValidator;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalReadiness;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\Gateways\FiscalGatewayManager;
use App\Domain\Fiscal\MenuFiscalLineBuilder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * P3-11: the fiscal layer (App\Domain\Fiscal, config/fiscal.php). The rest of the backend asks for FiscalIssuer,
 * FiscalLineBuilder and FiscalReadiness; drivers come from config('fiscal.drivers'). Pending receipts are swept
 * every minute (fiscal:retry-pending) in case their queued retry was lost. P6-16 adds the till's card flow section of
 * GET /pos/bootstrap (App\Domain\Pos\TerminalFlow).
 */
class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalGatewayManager::class);
        $this->app->singleton(FiscalDocumentValidator::class);
        $this->app->singleton(FiscalService::class);
        $this->app->singleton(FiscalIssuer::class, fn ($app) => $app->make(FiscalService::class));
        $this->app->singleton(FiscalLineBuilder::class, MenuFiscalLineBuilder::class);
        $this->app->singleton(FiscalReadiness::class);

        // P6-16: the till's card flow (bank terminal "faqat terminal" mode, smart kassa) in GET /pos/bootstrap
        $this->app->tag([\App\Domain\Pos\TerminalFlow\TerminalFlowBootstrapSection::class], \App\Domain\Pos\Setup\PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RetryPendingFiscalReceipts::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('fiscal:retry-pending')->everyMinute()->withoutOverlapping(10);
        });
    }
}
