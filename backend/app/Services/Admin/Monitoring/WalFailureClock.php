<?php

namespace App\Services\Admin\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3 (review R3-5): how long WAL archiving has been FAILING. pg_stat_archiver only says the last failure is newer
 * than the last success; on an idle server (archive_timeout writes nothing when nothing changes) the last success
 * can be hours old while the first failed attempt happened seconds ago, so "now - last success" is not the length of
 * an outage. The clock remembers when the failing state was first seen (reset when archiving succeeds again or the
 * last success moves). Without the cache it reports 0: a failure is never paged on a guess.
 */
final class WalFailureClock
{
    private const KEY = 'monitoring:wal:failing-since';

    /**
     * @param  array{failing: bool, last_archived_at: string|null}  $archiver
     * @return int|null seconds failing (as far as this clock has seen), null when not failing
     */
    public static function failingSeconds(array $archiver): ?int
    {
        try {
            if (!$archiver['failing']) {
                Cache::forget(self::KEY);

                return null;
            }
            $seen = Cache::get(self::KEY);
            if (!is_array($seen) || ($seen['last_archived_at'] ?? null) !== $archiver['last_archived_at']) {
                $seen = ['since' => now()->getTimestamp(), 'last_archived_at' => $archiver['last_archived_at']];
                Cache::put(self::KEY, $seen, 7 * 86400);
            }

            return max(0, now()->getTimestamp() - (int) $seen['since']);
        } catch (Throwable $e) {
            Log::warning('Monitoring: the WAL failure clock cannot use the cache', ['exception' => $e::class]);

            return $archiver['failing'] ? 0 : null;
        }
    }
}
