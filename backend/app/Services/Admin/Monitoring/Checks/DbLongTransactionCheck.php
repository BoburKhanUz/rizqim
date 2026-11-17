<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\PostgresStats;

/**
 * M3: a transaction of this database has been open longer than `threshold` seconds while working (subject `active`)
 * or `threshold_secondary` seconds while idle in transaction (subject `idle`: a forgotten BEGIN, the worst kind: it
 * holds its locks and stops vacuum). The backup tools' sessions are excluded (monitoring.postgres).
 */
class DbLongTransactionCheck implements AlertCheck
{
    public function __construct(
        private readonly PostgresStats $stats,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $state = $this->stats->longTransactions();
        if ($state === null) {
            return null;
        }

        $findings = [];
        foreach (['active' => $rule->threshold, 'idle' => $rule->threshold_secondary ?? $rule->threshold] as $kind => $limit) {
            $over = array_values(array_filter($state[$kind], fn ($tx) => $tx['seconds'] >= $limit));
            if ($over === []) {
                continue;
            }
            $longest = $over[0];
            $minutes = intdiv($longest['seconds'], 60);
            $title = $kind === 'active'
                ? "Bazada tranzaksiya {$minutes} daqiqadan beri ishlayapti (pid {$longest['pid']})"
                : "Bazada tranzaksiya {$minutes} daqiqadan beri ochiq qolib, hech narsa qilmayapti (pid {$longest['pid']})";
            $findings[] = new AlertFinding($kind, $title, [
                'count' => count($over),
                'longest_seconds' => $longest['seconds'],
                'threshold_seconds' => $limit,
                'sessions' => array_slice($over, 0, 5),
            ]);
        }

        return $findings;
    }
}
