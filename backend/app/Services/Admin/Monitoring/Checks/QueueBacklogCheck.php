<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\QueueInspector;

/**
 * A queue holds more than `threshold` jobs, or its oldest ready job waited longer than `threshold_secondary` minutes.
 * One subject per queue.
 */
class QueueBacklogCheck implements AlertCheck
{
    public function __construct(
        private readonly QueueInspector $queues,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $maxAgeSeconds = $rule->threshold_secondary !== null ? $rule->threshold_secondary * 60 : null;

        $findings = [];
        $readable = false;
        foreach ($this->queues->snapshot() as $queue) {
            if ($queue['size'] === null) {
                continue;
            }
            $readable = true;

            $tooMany = $queue['size'] > $rule->threshold;
            $tooOld = $maxAgeSeconds !== null && $queue['oldest_age_seconds'] !== null && $queue['oldest_age_seconds'] > $maxAgeSeconds;
            if (!$tooMany && !$tooOld) {
                continue;
            }

            $title = $tooMany
                ? "\"{$queue['name']}\" navbatida {$queue['size']} ta vazifa to'planib qoldi"
                : "\"{$queue['name']}\" navbatidagi eng eski vazifa " . intdiv((int) $queue['oldest_age_seconds'], 60) . ' daqiqadan beri kutmoqda';

            $findings[] = new AlertFinding($queue['name'], $title, [
                'queue' => $queue['name'],
                'size' => $queue['size'],
                'oldest_age_seconds' => $queue['oldest_age_seconds'],
                'threshold' => $rule->threshold,
                'threshold_age_minutes' => $rule->threshold_secondary,
            ]);
        }

        return $readable ? $findings : null;
    }
}
