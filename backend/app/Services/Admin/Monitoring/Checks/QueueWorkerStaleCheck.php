<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Jobs\QueueHeartbeatJob;
use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Support\HealthReport;

/**
 * M3: no queue worker ran QueueHeartbeatJob (dispatched every minute) within `threshold` seconds. While the scheduler
 * itself is stale nothing is dispatched, so this check cannot tell (unknown: scheduler_stale already alerts). A new
 * install gets `threshold` seconds from the rule's creation for the first heartbeat.
 */
class QueueWorkerStaleCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        if (!HealthReport::schedulerIsFresh()) {
            return null;
        }

        $beat = QueueHeartbeatJob::last();
        if ($beat !== null && $beat['age_seconds'] <= $rule->threshold) {
            return [];
        }
        if ($beat === null && $rule->created_at !== null && $rule->created_at->gt(now()->subSeconds($rule->threshold))) {
            return [];
        }

        return [new AlertFinding('global', $beat === null
            ? "Navbat ishchisi tekshiruv vazifasini bajarmadi: navbat ishlamayapti"
            : 'Navbat ishchisi ' . intdiv($beat['age_seconds'], 60) . ' daqiqadan beri vazifa bajarmayapti', [
                'last_heartbeat_age_seconds' => $beat['age_seconds'] ?? null,
                'threshold_seconds' => $rule->threshold,
                'queue' => (string) config('monitoring.queue_heartbeat_queue', 'default'),
            ])];
    }
}
