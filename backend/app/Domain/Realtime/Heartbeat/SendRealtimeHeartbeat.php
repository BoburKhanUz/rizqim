<?php

namespace App\Domain\Realtime\Heartbeat;

use App\Domain\Realtime\Broadcasting\RevisionStamp;
use App\Domain\Realtime\Health\RealtimeHealth;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave M2: every `realtime.heartbeat_seconds` (scheduler, RealtimeServiceProvider) one `realtime.heartbeat` event to
 * every occupied staff/POS channel (`pos-branch.*`, `branch.*`, `store.*`; Reverb's channel list), carrying the
 * channel's current change token: `{"at": "<ISO time>", "interval": 30, "rev": {"pos-branch.12": "4711"}}`.
 *
 * Runs on the broadcasts queue, the path every event takes: a stuck scheduler, a stuck or backed-up queue or an
 * unreachable Reverb all show up as silence on the client (no heartbeat for 3 intervals: poll fast) and as a stale
 * heartbeat in RealtimeHealth. A client whose token differs from the heartbeat's rev missed an event: it re-reads.
 * Customer channels (`user.*`) get none: the Mini App keeps its own safety poll.
 */
class SendRealtimeHeartbeat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const EVENT = 'realtime.heartbeat';

    /** Channel prefixes (as Reverb names them) that get a heartbeat */
    public const PREFIXES = ['private-pos-branch.', 'private-branch.', 'private-store.'];

    public int $tries = 1;

    public int $timeout = 25;

    public function __construct()
    {
        $this->onQueue((string) config('realtime.broadcast_queue', 'broadcasts'));
    }

    public function handle(): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }
        $broadcaster = Broadcast::driver();
        if (!$broadcaster instanceof PusherBroadcaster) {
            return;
        }
        $pusher = $broadcaster->getPusher();
        $interval = (int) config('realtime.heartbeat_seconds', 30);

        try {
            $channels = [];
            foreach (self::PREFIXES as $prefix) {
                $result = $pusher->getChannels(['filter_by_prefix' => $prefix]);
                foreach (array_keys((array) ($result->channels ?? [])) as $name) {
                    $channels[] = (string) $name;
                }
            }

            $at = now()->toJSON();
            foreach (array_chunk($channels, 10) as $chunk) {
                $batch = [];
                foreach ($chunk as $channel) {
                    $payload = RevisionStamp::apply([$channel], ['at' => $at, 'interval' => $interval]);
                    $batch[] = ['channel' => $channel, 'name' => self::EVENT, 'data' => json_encode($payload, JSON_THROW_ON_ERROR)];
                }
                $pusher->triggerBatch($batch);
            }

            RealtimeHealth::recordHeartbeat(count($channels));
        } catch (Throwable $e) {
            // Not recorded: RealtimeHealth turns degraded once the last good heartbeat is older than the stale limit
            Log::warning('Realtime: heartbeat not delivered', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }
}
