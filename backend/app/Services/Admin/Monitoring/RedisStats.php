<?php

namespace App\Services\Admin\Monitoring;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * M3: Redis memory (INFO memory) of the connection the queue and the revision counters use. With `noeviction` at the
 * cap, revision INCRs and queue pushes fail (docs/OPERATIONS.md), so the share of `maxmemory` is what matters.
 */
class RedisStats
{
    /**
     * null = Redis is not part of this deployment or could not be read.
     *
     * @return array{used_bytes: int, max_bytes: int|null, percent: float|null, policy: string|null}|null
     */
    public function memory(): ?array
    {
        if (!$this->used()) {
            return null;
        }

        try {
            $info = Redis::connection('default')->command('info', ['memory']);
        } catch (Throwable $e) {
            Log::warning('Monitoring: Redis memory could not be read', ['exception' => $e::class]);

            return null;
        }
        if (!is_array($info)) {
            return null;
        }
        // phpredis answers a flat array, predis one keyed by section
        $info = isset($info['Memory']) && is_array($info['Memory']) ? $info['Memory'] : $info;
        if (!isset($info['used_memory'])) {
            return null;
        }

        $used = (int) $info['used_memory'];
        $max = (int) ($info['maxmemory'] ?? 0);

        return [
            'used_bytes' => $used,
            'max_bytes' => $max > 0 ? $max : null,
            'percent' => $max > 0 ? round(100 * $used / $max, 1) : null,
            'policy' => isset($info['maxmemory_policy']) ? (string) $info['maxmemory_policy'] : null,
        ];
    }

    /**
     * Whether Redis answers a PING (only meaningful when used()).
     */
    public function reachable(): bool
    {
        try {
            Redis::connection('default')->ping();

            return true;
        } catch (Throwable $e) {
            Log::warning('Monitoring: Redis does not answer', ['exception' => $e::class]);

            return false;
        }
    }

    /** Whether this deployment uses Redis (cache, queue, sessions or the revision counters). */
    public function used(): bool
    {
        $store = (string) config('cache.default');

        return config("cache.stores.{$store}.driver") === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis'
            || config('realtime.revision_store') === 'redis';
    }
}
