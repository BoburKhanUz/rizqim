<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\PostgresStats;

/**
 * M3: open client connections reach `threshold` % of max_connections (subject `server`), or one client address holds
 * `threshold` % of PgBouncer's max_db_connections (subject `client:<address>`: the pool is exhausted, the tills'
 * requests queue behind it). Unknown when pg_stat_activity cannot be read.
 */
class DbConnectionsCheck implements AlertCheck
{
    public function __construct(
        private readonly PostgresStats $stats,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $state = $this->stats->connections();
        if ($state === null) {
            return null;
        }

        $findings = [];
        if ($state['percent'] >= $rule->threshold) {
            $findings[] = new AlertFinding('server', "Bazaga {$state['used']} ta ulanish ochiq ({$state['max']} tadan, " . (int) round($state['percent']) . ' %)', [
                'used' => $state['used'],
                'max' => $state['max'],
                'percent' => $state['percent'],
                'threshold_percent' => $rule->threshold,
                'clients' => $state['clients'],
            ]);
        }

        $poolMax = max(1, (int) config('monitoring.postgres.pgbouncer_max_connections', 30));
        foreach ($state['clients'] as $client) {
            if ($client['client'] === 'local' || 100 * $client['connections'] / $poolMax < $rule->threshold) {
                continue;
            }
            $findings[] = new AlertFinding('client:' . $client['client'], "{$client['client']} manzilidan bazaga {$client['connections']} ta ulanish (pul chegarasi {$poolMax})", [
                'client' => $client['client'],
                'connections' => $client['connections'],
                'pool_max' => $poolMax,
                'threshold_percent' => $rule->threshold,
            ]);
        }

        return $findings;
    }
}
