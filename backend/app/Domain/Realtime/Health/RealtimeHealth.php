<?php

namespace App\Domain\Realtime\Health;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Wave M2: is real-time safe to depend on right now? Live only when broadcasting goes to Reverb, the last heartbeat
 * (SendRealtimeHeartbeat, which runs on the broadcasts queue and is accepted by Reverb) is recent, and the broadcasts
 * queue is not backed up. Open sockets alone prove nothing (Wolt's incident: sockets open, no events), so the proof is
 * the same path every event takes: scheduler -> broadcasts queue -> worker -> Reverb.
 */
final class RealtimeHealth
{
    public const HEARTBEAT_KEY = 'realtime:heartbeat:ok';

    public static function recordHeartbeat(int $channels): void
    {
        try {
            self::store()->put(self::HEARTBEAT_KEY, ['at' => now()->getTimestamp(), 'channels' => $channels], 3600);
        } catch (Throwable $e) {
            Log::warning('Realtime: heartbeat could not be recorded', ['exception' => $e::class]);
        }
    }

    /**
     * @return array{status: string, live: bool, heartbeat_age_seconds: int|null, broadcast_backlog: int|null, channels: int|null}
     *   status: ok | degraded (on, but not to be trusted: clients poll fast) | off (BROADCAST_CONNECTION is not reverb)
     */
    public function status(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ['status' => 'off', 'live' => false, 'heartbeat_age_seconds' => null, 'broadcast_backlog' => null, 'channels' => null];
        }

        $beat = null;
        try {
            $beat = self::store()->get(self::HEARTBEAT_KEY);
        } catch (Throwable $e) {
            Log::warning('Realtime: heartbeat could not be read', ['exception' => $e::class]);
        }
        $age = is_array($beat) && isset($beat['at']) ? max(0, now()->getTimestamp() - (int) $beat['at']) : null;

        $backlog = null;
        try {
            $backlog = Queue::size((string) config('realtime.broadcast_queue', 'broadcasts'));
        } catch (Throwable $e) {
            Log::warning('Realtime: broadcast queue size could not be read', ['exception' => $e::class]);
        }

        $live = $age !== null && $age <= (int) config('realtime.heartbeat_stale_seconds', 120)
            && $backlog !== null && $backlog <= (int) config('realtime.queue_backlog_threshold', 500);

        return [
            'status' => $live ? 'ok' : 'degraded',
            'live' => $live,
            'heartbeat_age_seconds' => $age,
            'broadcast_backlog' => $backlog,
            'channels' => is_array($beat) ? (int) ($beat['channels'] ?? 0) : null,
        ];
    }

    private static function store(): Repository
    {
        return Cache::store((string) config('realtime.revision_store', 'redis'));
    }
}
