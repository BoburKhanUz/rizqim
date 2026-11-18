<?php

namespace App\Services\Admin\Monitoring;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * M3 (RR-8): the scheduler and queue heartbeats live in the cache (Redis in production). When the cache does not
 * answer, a missing heartbeat proves nothing about the scheduler or the worker: their checks say "unknown" and
 * redis_memory's `down` finding is the one message.
 */
final class CacheProbe
{
    public static function readable(): bool
    {
        try {
            Cache::get('monitoring:cache-probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
