<?php

namespace App\Jobs;

use App\Services\Admin\Today\PlatformStatsRefresher;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * P4-13: keeps the Bugun aggregate tables fresh (App\Services\Admin\Today\PlatformStatsRefresher). Scheduled every
 * 5 minutes for today and yesterday, and at 00:20 Asia/Tashkent for the last 8 days, so orders cancelled or refunded
 * later and the "same weekday last week" comparison stay right (AdminTodayServiceProvider). Not queued: it runs
 * inside the scheduler, needing no queue worker.
 */
class RefreshPlatformStatsJob
{
    use Dispatchable;

    public function __construct(
        public readonly int $days = 2,
    ) {}

    public function handle(PlatformStatsRefresher $refresher): void
    {
        $refreshed = $refresher->refreshRecent($this->days);

        if ($this->days > 2) {
            Log::info('Platform stats refreshed for ' . implode(', ', $refreshed));
        }
    }
}
