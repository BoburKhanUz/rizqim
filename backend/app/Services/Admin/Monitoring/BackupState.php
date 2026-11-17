<?php

namespace App\Services\Admin\Monitoring;

use App\Models\MonitoringMarker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * M3: what the backup service reports (monitoring_markers `pitr` and `disk`, every 5 minutes; scripts/backup.sh
 * write_markers) and the last restore drills (restore_drills, scripts/restore-drill.sh), read once for the checks
 * (pitr_backup_stale, disk_usage, restore_drill_stale) and for Monitoring → Holat.
 *
 * PITR counts as on when PITR_ENABLED is set for the backend (monitoring.pitr.enabled) or a fresh `pitr` marker says
 * so: on a fresh install with neither, everything PITR-related is "off" and nothing alerts.
 */
class BackupState
{
    /** A marker older than this many minutes means the backup service stopped reporting (rule window overrides). */
    public const MARKER_MAX_AGE_MINUTES = 15;

    public function marker(string $key): ?MonitoringMarker
    {
        try {
            return MonitoringMarker::query()->find($key);
        } catch (Throwable $e) {
            Log::warning('Monitoring: marker could not be read', ['key' => $key, 'exception' => $e::class]);

            return null;
        }
    }

    public function markerAgeSeconds(?MonitoringMarker $marker): ?int
    {
        return $marker === null ? null : max(0, (int) floor(Carbon::instance($marker->recorded_at)->diffInSeconds(now(), true)));
    }

    public function pitrEnabled(?MonitoringMarker $pitr = null, int $maxAgeMinutes = self::MARKER_MAX_AGE_MINUTES): bool
    {
        if ((bool) config('monitoring.pitr.enabled')) {
            return true;
        }
        $pitr ??= $this->marker(MonitoringMarker::PITR);

        return $pitr !== null
            && ($pitr->meta['enabled'] ?? false) === true
            && ($this->markerAgeSeconds($pitr) ?? PHP_INT_MAX) <= $maxAgeMinutes * 60;
    }

    /**
     * The newest drill and the newest successful one; null when the table is not there (migration not run).
     *
     * last_app_ok: the newest passed drill whose app check (db:restore-smoke) passed too (checks.app_smoke = ok).
     *
     * @return array{last: object|null, last_ok: object|null, last_app_ok: object|null}|null
     */
    public function drills(): ?array
    {
        try {
            if (!Schema::hasTable('restore_drills')) {
                return null;
            }

            return [
                'last' => DB::table('restore_drills')->orderByDesc('started_at')->orderByDesc('id')->first(),
                'last_ok' => DB::table('restore_drills')->where('status', 'ok')->orderByDesc('started_at')->orderByDesc('id')->first(),
                'last_app_ok' => DB::table('restore_drills')->where('status', 'ok')->whereRaw("checks->>'app_smoke' = 'ok'")->orderByDesc('started_at')->orderByDesc('id')->first(),
            ];
        } catch (Throwable $e) {
            Log::warning('Monitoring: restore drills could not be read', ['exception' => $e::class]);

            return null;
        }
    }

    /**
     * The drill's `checks` json as an array (null, a string from psql, or already decoded).
     *
     * @return array<string, mixed>
     */
    public static function checks(?object $drill): array
    {
        $checks = $drill->checks ?? null;
        if (is_string($checks)) {
            $checks = json_decode($checks, true);
        }

        return is_array($checks) ? $checks : [];
    }

    public static function parseTime(mixed $value): ?Carbon
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }

    public static function ageSeconds(?Carbon $time): ?int
    {
        return $time === null ? null : max(0, (int) floor($time->diffInSeconds(now(), true)));
    }
}
