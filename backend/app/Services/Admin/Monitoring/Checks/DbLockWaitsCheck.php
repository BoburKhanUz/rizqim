<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\PostgresStats;

/**
 * M3: at least `threshold` sessions of this database have waited on a lock for longer than `threshold_secondary`
 * seconds.
 */
class DbLockWaitsCheck implements AlertCheck
{
    public function __construct(
        private readonly PostgresStats $stats,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $waits = $this->stats->lockWaits();
        if ($waits === null) {
            return null;
        }

        $limit = $rule->threshold_secondary ?? 30;
        $long = array_values(array_filter($waits, fn ($wait) => $wait['seconds'] >= $limit));
        if (count($long) < $rule->threshold) {
            return [];
        }

        return [new AlertFinding('global', count($long) . " ta so'rov {$limit} soniyadan ortiq qulf kutmoqda", [
            'count' => count($long),
            'longest_seconds' => $long[0]['seconds'] ?? 0,
            'threshold' => $rule->threshold,
            'threshold_seconds' => $limit,
            'pids' => array_slice(array_column($long, 'pid'), 0, 10),
        ])];
    }
}
