<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\PostgresStats;

/**
 * M3: a hot table (monitoring.bloat.tables) carries dead rows above `threshold` % of its live rows and above
 * monitoring.bloat.min_dead_rows, or has not been vacuumed for `threshold_secondary` hours while it holds at least
 * monitoring.bloat.vacuum_min_dead_rows dead rows (a table nobody writes to needs no vacuum). One subject per table.
 */
class TableBloatCheck implements AlertCheck
{
    public function __construct(
        private readonly PostgresStats $stats,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $tables = $this->stats->tableStats(array_values((array) config('monitoring.bloat.tables', [])));
        if ($tables === null) {
            return null;
        }

        $minDead = (int) config('monitoring.bloat.min_dead_rows', 100000);
        $vacuumMinDead = (int) config('monitoring.bloat.vacuum_min_dead_rows', 10000);
        $hours = $rule->threshold_secondary ?? 24;
        $findings = [];
        foreach ($tables as $table) {
            $percent = $table['live'] > 0 ? round(100 * $table['dead'] / $table['live'], 1) : ($table['dead'] > 0 ? 100.0 : 0.0);
            $bloated = $table['dead'] >= $minDead && $percent >= $rule->threshold;
            $unvacuumed = $table['dead'] >= $vacuumMinDead
                && ($table['vacuum_age_seconds'] === null || $table['vacuum_age_seconds'] >= $hours * 3600);
            if (!$bloated && !$unvacuumed) {
                continue;
            }

            $findings[] = new AlertFinding($table['table'], $bloated
                ? "{$table['table']} jadvalida " . number_format($table['dead'], 0, '.', ' ') . " ta o'lik qator ({$percent} %)"
                : "{$table['table']} jadvali {$hours} soatdan beri tozalanmagan (" . number_format($table['dead'], 0, '.', ' ') . " ta o'lik qator)", [
                    'table' => $table['table'],
                    'dead_rows' => $table['dead'],
                    'live_rows' => $table['live'],
                    'dead_percent' => $percent,
                    'last_vacuum_at' => $table['last_vacuum_at'],
                    'threshold_percent' => $rule->threshold,
                    'threshold_hours' => $hours,
                ]);
        }

        return $findings;
    }
}
