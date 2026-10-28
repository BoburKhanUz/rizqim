<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * P4-31: Boshqaruv markazi → Ko'rsatkichlar and Excel exports. The rate limit of the exports: a file is built from up
 * to config('admin.export.max_rows') rows, so a member may start a few a minute (config('admin.export.per_minute')).
 */
class AdminMetricsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('admin-export', fn (Request $request) => Limit::perMinute((int) config('admin.export.per_minute', 10))
            ->by('admin-export:' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
