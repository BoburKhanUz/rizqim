<?php

namespace App\Domain\Realtime\Health;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Wave M2: the Reverb container's healthcheck (docker-compose.yml). Unhealthy (exit 1) when its own /up does not
 * answer, when it scales over Redis and Redis does not answer (it would hold sockets but receive no events), or when
 * real-time is not live: the heartbeat is stale or the broadcasts queue is backed up (RealtimeHealth). Prints one line.
 */
class RealtimeHealthCommand extends Command
{
    protected $signature = 'realtime:health {--local-port=8080 : the Reverb server port inside its container}';

    protected $description = "Real-time (Reverb) sog'lig'ini tekshiradi: server, Redis, heartbeat va broadcasts navbati";

    public function handle(RealtimeHealth $health): int
    {
        if (config('broadcasting.default') !== 'reverb') {
            $this->line('off: BROADCAST_CONNECTION reverb emas');

            return self::SUCCESS;
        }

        try {
            if (!Http::timeout(3)->get('http://127.0.0.1:' . (int) $this->option('local-port') . '/up')->successful()) {
                $this->error('fail: Reverb /up javob bermadi');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('fail: Reverb /up ga ulanib bo\'lmadi');

            return self::FAILURE;
        }

        if (config('reverb.servers.reverb.scaling.enabled')) {
            try {
                Redis::connection()->ping();
            } catch (Throwable) {
                $this->error('fail: Reverb Redis orqali event ololmaydi (Redis javob bermadi)');

                return self::FAILURE;
            }
        }

        $status = $health->status();
        if (!$status['live']) {
            $this->error(sprintf('degraded: heartbeat %s s oldin, broadcasts navbatida %s ta job', $status['heartbeat_age_seconds'] ?? '-', $status['broadcast_backlog'] ?? '-'));

            return self::FAILURE;
        }

        $this->line(sprintf('ok: heartbeat %d s oldin, %d kanal', (int) $status['heartbeat_age_seconds'], (int) $status['channels']));

        return self::SUCCESS;
    }
}
