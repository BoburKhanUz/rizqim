<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\RedisStats;

/**
 * M3: Redis uses at least `threshold` % (warning) or `threshold_secondary` % (critical) of its maxmemory. Without a
 * maxmemory there is no cap to reach (nothing reported). Redis used but not answering a PING: critical `down` (one
 * clear message: the queue and scheduler checks then say "unknown" instead of blaming the worker). Not used: nothing.
 */
class RedisMemoryCheck implements AlertCheck
{
    public function __construct(
        private readonly RedisStats $redis,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $memory = $this->redis->memory();
        if ($memory === null) {
            // Used but not answering is an outage, not "unknown": the queue, the cache and real-time stop (RR-8)
            if ($this->redis->used() && !$this->redis->reachable()) {
                return [new AlertFinding('down', "Redis javob bermayapti: navbat (Telegram xabarlari, fon vazifalari), kesh va real vaqt ishlamayapti", [], null, 'critical')];
            }

            return null;
        }
        if ($memory['percent'] === null || $memory['percent'] < $rule->threshold) {
            return [];
        }

        $critical = $rule->threshold_secondary ?? 90;

        return [new AlertFinding('global', 'Redis xotirasi ' . (int) round($memory['percent']) . ' % band (' . DiskUsageCheck::size($memory['used_bytes']) . ' / ' . DiskUsageCheck::size((int) $memory['max_bytes']) . ')', [
            'used_bytes' => $memory['used_bytes'],
            'max_bytes' => $memory['max_bytes'],
            'percent' => $memory['percent'],
            'policy' => $memory['policy'],
            'threshold_percent' => $rule->threshold,
            'critical_percent' => $critical,
        ], null, $memory['percent'] >= $critical ? 'critical' : 'warning')];
    }
}
