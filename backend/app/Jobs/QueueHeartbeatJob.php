<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3: proof that a queue worker takes jobs. The scheduler dispatches it every minute to the `default` queue
 * (App\Providers\AdminMonitoringServiceProvider); the worker that runs it writes the time to the cache. The alert
 * rule queue_worker_stale fires when that time is older than its threshold. One try, no retries: the next minute is
 * the retry, and an old heartbeat is never "refreshed" late.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const KEY = 'monitoring:queue-heartbeat';

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $dispatchedAt = 0)
    {
        $this->dispatchedAt = $dispatchedAt ?: now()->getTimestamp();
        $this->onQueue((string) config('monitoring.queue_heartbeat_queue', 'default'));
    }

    public function handle(): void
    {
        // A job that waited in the queue longer than a rule would allow still proves the worker runs, but the wait is
        // kept so the page can show it
        Cache::put(self::KEY, ['at' => now()->getTimestamp(), 'waited' => max(0, now()->getTimestamp() - $this->dispatchedAt)], 3600);
    }

    /**
     * @return array{age_seconds: int, waited_seconds: int}|null null = no heartbeat (never ran, or the cache was cleared)
     */
    public static function last(): ?array
    {
        try {
            $beat = Cache::get(self::KEY);
        } catch (Throwable $e) {
            Log::warning('Monitoring: queue heartbeat could not be read', ['exception' => $e::class]);

            return null;
        }
        if (!is_array($beat) || !isset($beat['at'])) {
            return null;
        }

        return ['age_seconds' => max(0, now()->getTimestamp() - (int) $beat['at']), 'waited_seconds' => (int) ($beat['waited'] ?? 0)];
    }
}
