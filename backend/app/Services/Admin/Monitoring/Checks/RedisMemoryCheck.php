<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\RedisStats;

/**
 * M3: Redis uses at least `threshold` % (warning) or `threshold_secondary` % (critical) of its maxmemory. Without a
 * maxmemory there is no cap to reach (nothing reported); Redis not used or not readable: unknown (its own outage
 * is the health report's "fail").
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
