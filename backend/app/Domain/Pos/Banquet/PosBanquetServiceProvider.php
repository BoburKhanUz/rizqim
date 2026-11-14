<?php

namespace App\Domain\Pos\Banquet;

use App\Domain\Pos\Banquet\Console\ReleaseExpiredHolds;
use App\Domain\Pos\Setup\PosBootstrap;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * P6-51/P6-52: banquet events. The minute job releasing expired holds (pos:banquet-release-holds) and the `banquet`
 * section of GET /pos/bootstrap (BanquetBootstrapSection), and the limiter of the phone search (pos-banquet-search).
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

        // GET /pos/banquets?phone=: a search of customers by phone digits — 20 a minute per staff member and per
        // terminal (the list by dates is not limited beyond throttle:api)
        RateLimiter::for('pos-banquet-search', function (Request $request) {
            if (!$request->filled('phone')) {
                return Limit::none();
            }
            $user = $request->user();
            $deviceId = $user !== null ? data_get($user->currentAccessToken(), 'device_id') : null;
            $refused = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => "Telefon bo'yicha qidiruv juda ko'p. Bir daqiqadan so'ng qayta urinib ko'ring.",
                'data' => ['reason' => 'search_rate_limited'],
            ], 429, $headers);

            $limits = [Limit::perMinute(20)->by('pos-banquet-search:user:' . ($user?->getAuthIdentifier() ?? $request->ip()))->response($refused)];
            if ($deviceId !== null) {
                $limits[] = Limit::perMinute(20)->by('pos-banquet-search:device:' . $deviceId)->response($refused);
            }

            return $limits;
        });
    }
}
