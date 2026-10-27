<?php

namespace App\Services\Admin\Monitoring;

use App\Support\HealthReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * P4-15: the queues the app uses with their sizes (HealthReport::backlog) and how long the oldest waiting job has
 * waited. Reads the database or Redis queue; any other driver (sync in tests) has no age.
 */
class QueueInspector
{
    public function __construct(
        private readonly HealthReport $health,
    ) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique(array_filter([
            'default',
            \App\Events\Broadcast\OrderBroadcast::QUEUE,
            (string) config('orders.reports.queue', 'reports'),
            (string) config('menu.images.queue', 'default'),
            (string) config('fiscal.retry.queue', 'default'),
        ], fn (string $name) => $name !== '')));
    }

    /**
     * @return list<array{name: string, size: int|null, oldest_age_seconds: int|null}>
     */
    public function snapshot(): array
    {
        return array_map(fn (string $name) => [
            'name' => $name,
            'size' => $this->health->backlog($name),
            'oldest_age_seconds' => $this->oldestAgeSeconds($name),
        ], $this->names());
    }

    /**
     * Seconds the oldest job that is ready to run has been waiting; null for an empty queue or when it cannot be read.
     */
    public function oldestAgeSeconds(string $queue): ?int
    {
        try {
            $connection = (string) config('queue.default');
            $driver = (string) config("queue.connections.{$connection}.driver");

            if ($driver === 'database') {
                $table = (string) config("queue.connections.{$connection}.table", 'jobs');
                $oldest = DB::table($table)
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->where('available_at', '<=', time())
                    ->min('available_at');

                return $oldest === null ? null : max(0, time() - (int) $oldest);
            }

            if ($driver === 'redis') {
                $redis = Redis::connection((string) config("queue.connections.{$connection}.connection", 'default'));
                $head = $redis->lindex("queues:{$queue}", 0);
                $pushedAt = is_string($head) ? (json_decode($head, true)['pushedAt'] ?? null) : null;

                return is_numeric($pushedAt) ? max(0, time() - (int) floor((float) $pushedAt)) : null;
            }
        } catch (Throwable $e) {
            Log::warning('Monitoring: oldest queue job age could not be read', ['queue' => $queue, 'exception' => $e::class]);
        }

        return null;
    }
}
