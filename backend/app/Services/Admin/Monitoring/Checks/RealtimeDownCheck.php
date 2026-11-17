<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Domain\Realtime\Health\RealtimeHealth;
use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use Illuminate\Support\Facades\Cache;

/**
 * M3: real-time is switched on (BROADCAST_CONNECTION=reverb) but RealtimeHealth has not called it live for
 * `threshold` minutes (its heartbeat goes scheduler -> broadcasts queue -> worker -> Reverb). Real-time switched off:
 * nothing to report.
 */
class RealtimeDownCheck implements AlertCheck
{
    private const SINCE_KEY = 'monitoring:realtime:down-since';

    public function __construct(
        private readonly RealtimeHealth $realtime,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $status = $this->realtime->status();
        if ($status['status'] === 'off' || $status['live']) {
            Cache::forget(self::SINCE_KEY);

            return [];
        }

        Cache::add(self::SINCE_KEY, now()->getTimestamp(), 86400);
        $since = (int) Cache::get(self::SINCE_KEY, now()->getTimestamp());
        $seconds = max(0, now()->getTimestamp() - $since);
        if ($seconds < $rule->threshold * 60) {
            return [];
        }

        return [new AlertFinding('global', 'Real vaqt yangilanishlari ' . intdiv($seconds, 60) . " daqiqadan beri ishonchsiz: ilovalar so'rov bilan ishlayapti", [
            'down_seconds' => $seconds,
            'heartbeat_age_seconds' => $status['heartbeat_age_seconds'],
            'broadcast_backlog' => $status['broadcast_backlog'],
            'threshold_minutes' => $rule->threshold,
        ])];
    }
}
