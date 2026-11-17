<?php

namespace App\Domain\Realtime\Http;

use App\Domain\Realtime\Health\RealtimeHealth;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Group('Health')]
class RealtimeStatusController extends Controller
{
    use ApiResponse;

    /**
     * Real-time status and connection settings
     *
     * Public, no database. `live` false (Reverb off, the heartbeat stale or the broadcasts queue backed up): clients
     * poll at `poll_seconds.fallback`; live: they listen and poll at `poll_seconds.live` as a safety net, and treat
     * `silence_seconds` without any event or `realtime.heartbeat` on a channel as "not live". `reverb` is what a client
     * connects with (the public app key; null host = the API's own host); null when real-time is off. Read it on start
     * and on every reconnect, not on every poll. Kept for 5 seconds.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{status: string, live: bool, heartbeat_seconds: int, silence_seconds: int, poll_seconds: array{live: int, fallback: int}, reverb: array{key: string, host: string|null, port: int|null, scheme: string|null}|null}}')]
    public function __invoke(): JsonResponse
    {
        $data = null;
        try {
            $data = Cache::store((string) config('realtime.revision_store', 'redis'))->remember('realtime:status', 5, fn () => $this->build());
        } catch (Throwable) {
            $data = $this->build();
        }

        return $this->success($data)->header('Cache-Control', 'no-store, max-age=0');
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $health = app(RealtimeHealth::class)->status();
        $heartbeat = (int) config('realtime.heartbeat_seconds', 30);
        $key = (string) config('broadcasting.connections.reverb.key', '');

        return [
            'status' => $health['status'],
            'live' => $health['live'],
            'heartbeat_seconds' => $heartbeat,
            'silence_seconds' => $heartbeat > 0 ? 3 * $heartbeat : 0,
            'poll_seconds' => [
                'live' => (int) config('realtime.poll_live_seconds', 60),
                'fallback' => (int) config('realtime.poll_fallback_seconds', 15),
            ],
            'reverb' => $health['status'] !== 'off' && $key !== '' ? [
                'key' => $key,
                'host' => config('realtime.public.host') ?: null,
                'port' => config('realtime.public.port') ? (int) config('realtime.public.port') : null,
                'scheme' => config('realtime.public.scheme') ?: null,
            ] : null,
        ];
    }
}
