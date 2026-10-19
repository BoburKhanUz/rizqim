<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * What GET /health reports. "fail" (HTTP 503) only for the database or Redis, because the app cannot serve without
 * them; a stale scheduler or a long queue is "degraded" (HTTP 200) so a Docker healthcheck does not restart a working
 * web container because of a background problem. Nothing here exposes versions, hosts, credentials or exception text
 * (details go to the log).
 */
class HealthReport
{
    public const HEARTBEAT_KEY = 'health:scheduler-heartbeat';

    /** Scheduled every minute (bootstrap/app.php): proves the scheduler process is alive. */
    public static function beat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, time(), 600);
    }

    /** Seconds since the last heartbeat, null if there was none (never ran, or the cache was cleared). */
    public static function heartbeatAge(): ?int
    {
        try {
            $beat = Cache::get(self::HEARTBEAT_KEY);
        } catch (Throwable $e) {
            Log::warning('Health: heartbeat could not be read', ['exception' => $e::class]);

            return null;
        }

        return is_numeric($beat) ? max(0, time() - (int) $beat) : null;
    }

    public static function schedulerIsFresh(): bool
    {
        $age = self::heartbeatAge();

        return $age !== null && $age <= (int) config('health.scheduler_max_age');
    }

    /**
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function report(): array
    {
        $database = $this->database();
        $redis = $this->redis();
        $queue = $this->queue();
        $scheduler = $this->scheduler();

        if ($database['status'] === 'fail' || $redis['status'] === 'fail') {
            $status = 'fail';
        } elseif ($scheduler['status'] !== 'ok' || $queue['status'] !== 'ok') {
            $status = 'degraded';
        } else {
            $status = 'ok';
        }

        return [
            'status' => $status,
            'checks' => [
                'database' => $database,
                'redis' => $redis,
                'queue' => $queue,
                'scheduler' => $scheduler,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function database(): array
    {
        try {
            $started = microtime(true);
            DB::select('select 1');

            return ['status' => 'ok', 'latency_ms' => round((microtime(true) - $started) * 1000, 1)];
        } catch (Throwable $e) {
            Log::error('Health: database check failed', ['exception' => $e::class]);

            return ['status' => 'fail'];
        }
    }

    /** @return array<string, mixed> */
    private function redis(): array
    {
        if (!$this->redisIsUsed()) {
            return ['status' => 'not_used'];
        }

        try {
            $started = microtime(true);
            // The queue and the sessions use the "default" connection, the cache uses "cache" (separate database)
            foreach (['default', 'cache'] as $connection) {
                Redis::connection($connection)->ping();
            }

            return ['status' => 'ok', 'latency_ms' => round((microtime(true) - $started) * 1000, 1)];
        } catch (Throwable $e) {
            Log::error('Health: redis check failed', ['exception' => $e::class]);

            return ['status' => 'fail'];
        }
    }

    private function redisIsUsed(): bool
    {
        $cacheStore = (string) config('cache.default');

        return config("cache.stores.{$cacheStore}.driver") === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }

    /** @return array<string, mixed> */
    private function queue(): array
    {
        $default = $this->backlog('default');
        $reports = $this->backlog((string) config('orders.reports.queue', 'reports'));

        $status = 'ok';
        if ($default === null || $reports === null) {
            $status = 'unknown';
        } elseif ($default > (int) config('health.queue_backlog_warn') || $reports > (int) config('health.reports_backlog_warn')) {
            $status = 'backlog';
        }

        return ['status' => $status, 'default_backlog' => $default, 'reports_backlog' => $reports];
    }

    private function backlog(string $queue): ?int
    {
        try {
            return Queue::size($queue);
        } catch (Throwable $e) {
            Log::warning('Health: queue size could not be read', ['exception' => $e::class]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function scheduler(): array
    {
        $age = self::heartbeatAge();

        return [
            'status' => $age === null ? 'missing' : ($age > (int) config('health.scheduler_max_age') ? 'stale' : 'ok'),
            'last_heartbeat_age_seconds' => $age,
        ];
    }
}
