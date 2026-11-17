<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;

/**
 * M3: the backup service's `disk` marker (every 5 minutes): the database filesystem (subject `data`) and the local
 * backup repository's (subject `repo`) are at least `threshold` % full (warning) or `threshold_secondary` % (critical).
 * A marker that stopped coming for `window_minutes` is critical (subject `marker`: the disk can no longer be watched,
 * and the backup service is down). No marker at all (a backup service that was never rebuilt for M3) is unknown.
 */
class DiskUsageCheck implements AlertCheck
{
    public function __construct(
        private readonly BackupState $backups,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $marker = $this->backups->marker(MonitoringMarker::DISK);
        if ($marker === null) {
            return null;
        }

        $window = max(1, (int) ($rule->window_minutes ?? BackupState::MARKER_MAX_AGE_MINUTES));
        $age = (int) $this->backups->markerAgeSeconds($marker);
        if ($age > $window * 60) {
            return [new AlertFinding('marker', 'Zaxira xizmati ' . intdiv($age, 60) . " daqiqadan beri disk holatini yozmayapti: backup xizmati to'xtagan bo'lishi mumkin", [
                'marker_at' => $marker->recorded_at->toIso8601String(),
                'marker_age_seconds' => $age,
                'threshold_minutes' => $window,
            ], null, 'critical')];
        }

        $meta = (array) $marker->meta;
        $critical = $rule->threshold_secondary ?? 90;
        $findings = [];
        foreach (['data' => 'used_percent', 'repo' => 'repo_used_percent'] as $subject => $field) {
            if (!isset($meta[$field]) || !is_numeric($meta[$field])) {
                continue;
            }
            $percent = (float) $meta[$field];
            if ($percent < $rule->threshold) {
                continue;
            }
            $free = $subject === 'data' ? ($meta['fs_avail_bytes'] ?? null) : ($meta['repo_avail_bytes'] ?? null);
            $what = $subject === 'data' ? 'Baza turgan disk' : 'Zaxira ombori diski';
            $findings[] = new AlertFinding($subject, "{$what} " . (int) round($percent) . ' % to\'lgan' . (is_numeric($free) ? ', bo\'sh joy ' . self::size((int) $free) : ''), [
                'used_percent' => $percent,
                'avail_bytes' => is_numeric($free) ? (int) $free : null,
                'pg_wal_bytes' => isset($meta['pg_wal_bytes']) ? (int) $meta['pg_wal_bytes'] : null,
                'spool_bytes' => isset($meta['spool_bytes']) ? (int) $meta['spool_bytes'] : null,
                'threshold_percent' => $rule->threshold,
                'critical_percent' => $critical,
            ], null, $percent >= $critical ? 'critical' : 'warning');
        }

        return $findings;
    }

    public static function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max(0, $bytes);
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $value : number_format($value, 1, '.', '')) . ' ' . $units[$unit];
    }
}
