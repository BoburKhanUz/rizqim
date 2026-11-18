<?php

namespace App\Services\Admin\Monitoring;

use App\Models\MonitoringMarker;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3 (review R3-5): how long WAL archiving has been FAILING. pg_stat_archiver only says the last failure is newer
 * than the last success; on an idle server (archive_timeout writes nothing when nothing changes) the last success
 * can be hours old while the first failed attempt happened seconds ago, so "now - last success" is not the length of
 * an outage. The clock remembers (in monitoring_markers) when the failing state was first seen, reset when archiving
 * succeeds again or the last success moves. If it cannot be read it reports 0: a failure is never paged on a guess.
 */
final class WalFailureClock
{
    /** monitoring_markers key (in the database, not the cache: a Redis outage must not hide a WAL outage, RR-8). */
    public const MARKER = 'wal_failing';

    /**
     * @param  array{failing: bool, last_archived_at: string|null}  $archiver
     * @return int|null seconds failing (as far as this clock has seen), null when not failing
     */
    public static function failingSeconds(array $archiver): ?int
    {
        try {
            if (!$archiver['failing']) {
                MonitoringMarker::query()->whereKey(self::MARKER)->delete();

                return null;
            }
            $seen = MonitoringMarker::query()->find(self::MARKER);
            $meta = $seen !== null ? (array) $seen->meta : [];
            if ($seen === null || ($meta['last_archived_at'] ?? null) !== $archiver['last_archived_at']) {
                MonitoringMarker::query()->updateOrCreate(['key' => self::MARKER], ['recorded_at' => now(), 'meta' => ['last_archived_at' => $archiver['last_archived_at']]]);

                return 0;
            }

            return max(0, (int) floor($seen->recorded_at->diffInSeconds(now(), true)));
        } catch (Throwable $e) {
            Log::warning('Monitoring: the WAL failure clock cannot be read', ['exception' => $e::class]);

            return $archiver['failing'] ? 0 : null;
        }
    }
}
