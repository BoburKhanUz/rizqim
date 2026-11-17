<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\PostgresStats;
use App\Support\Partitions\PartitionManager;

/**
 * M3: the journals' monthly partitions (PartitionManager::status, the same report as `db:partitions:status`). Fewer
 * than `threshold` future months attached (or the current one missing) is critical: the next month's rows would land
 * in DEFAULT; rows in DEFAULT, a table not converted yet, an unfinished conversion and archive failures or stale
 * detached partitions are warnings. One subject per problem and table; the texts are the status report's.
 */
class PartitionsCheck implements AlertCheck
{
    public function __construct(
        private readonly PartitionManager $partitions,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $status = PostgresStats::bounded(fn () => $this->partitions->status(), 'partitions');
        if ($status === null) {
            return null;
        }

        $findings = [];
        foreach ($status['tables'] as $table) {
            if (!$table['partitioned']) {
                continue;
            }
            if (!$table['current_month_attached'] || $table['future_months'] < $rule->threshold) {
                $findings[] = new AlertFinding("future_months:{$table['table']}", "{$table['table']}: oldinda {$table['future_months']} oy tayyor (kamida {$rule->threshold} kerak): `php artisan db:partitions:ensure` bajaring", [
                    'table' => $table['table'],
                    'future_months' => $table['future_months'],
                    'current_month_attached' => $table['current_month_attached'],
                    'threshold_months' => $rule->threshold,
                ], null, 'critical');
            }
        }

        foreach ($status['problems'] as $problem) {
            // future_months_low is measured above against the rule's own threshold
            if ($problem['code'] === 'future_months_low') {
                continue;
            }
            $findings[] = new AlertFinding($problem['code'] . ':' . ($problem['table'] ?? 'global'), $problem['message'], [
                'table' => $problem['table'],
                'code' => $problem['code'],
            ], null, 'warning');
        }

        return $findings;
    }
}
