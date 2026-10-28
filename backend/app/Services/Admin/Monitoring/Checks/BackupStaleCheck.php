<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertFinding;

/**
 * The backup service (scripts/backup.sh) records `backup` after every verified dump; stale = older than
 * `threshold` hours. Without a marker the alert waits until the rule itself is that old, so a fresh install or a
 * backup service that was not rebuilt yet does not alert at once.
 */
class BackupStaleCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        $maxAge = now()->subHours($rule->threshold);
        $marker = MonitoringMarker::query()->find(MonitoringMarker::BACKUP);

        if ($marker === null) {
            if ($rule->created_at !== null && $rule->created_at->gt($maxAge)) {
                return [];
            }

            return [new AlertFinding('global', 'Zaxira nusxa hech qachon qayd etilmagan', [
                'last_backup_at' => null,
                'threshold_hours' => $rule->threshold,
            ])];
        }

        if ($marker->recorded_at->gte($maxAge)) {
            return [];
        }

        $hours = (int) floor($marker->recorded_at->diffInHours(now(), true));

        return [new AlertFinding('global', "Oxirgi zaxira nusxa {$hours} soat oldin olingan", [
            'last_backup_at' => $marker->recorded_at->toIso8601String(),
            'age_hours' => $hours,
            'threshold_hours' => $rule->threshold,
        ])];
    }
}
