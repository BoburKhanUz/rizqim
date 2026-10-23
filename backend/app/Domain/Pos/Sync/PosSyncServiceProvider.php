<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Models\Device;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * P3-31: the offline limited mode (docs/POS_SPEC.md §5): the `offline` section of GET /pos/bootstrap (what a till keeps
 * to sell through an outage) and the rate limit of POST /pos/sync (a drained queue after an hour comes in a few dozen
 * batches; per till).
 */
class PosSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([OfflineBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        RateLimiter::for('pos-sync', fn (Request $request) => Limit::perMinute(120)->by(
            ($device = $request->user('device')) instanceof Device ? 'pos-sync:' . $device->id : 'ip:' . $request->ip()
        ));
    }
}
