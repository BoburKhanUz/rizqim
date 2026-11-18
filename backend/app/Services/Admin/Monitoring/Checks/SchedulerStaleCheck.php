<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Support\HealthReport;

/**
 * The scheduler's heartbeat (HealthReport::beat, every minute) is missing or older than `threshold` seconds.
 */
class SchedulerStaleCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        // The heartbeat is in the cache: a cache that does not answer says nothing about the scheduler (RR-8)
        if (!\App\Services\Admin\Monitoring\CacheProbe::readable()) {
            return null;
        }
        $age = HealthReport::heartbeatAge();

        if ($age !== null && $age <= $rule->threshold) {
            return [];
        }

        return [new AlertFinding('global', $age === null
            ? 'Scheduler yurak urishi topilmadi'
            : 'Scheduler ' . intdiv($age, 60) . ' daqiqadan beri yurak urishini yozmayapti', [
                'last_heartbeat_age_seconds' => $age,
                'threshold_seconds' => $rule->threshold,
            ])];
    }
}
