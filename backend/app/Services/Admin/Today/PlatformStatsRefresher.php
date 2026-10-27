<?php

namespace App\Services\Admin\Today;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-13: writes one local day of the aggregate tables (platform_hourly_stats, platform_daily_stats) from scratch and
 * records how far its hourly rows are final (platform_stats_days.complete_through: the start of the current hour for
 * today, the end of the day for a past day). Recomputing a day is idempotent, so the job may run again any time and
 * picks up orders cancelled or refunded after they were counted.
 */
class PlatformStatsRefresher
{
    public function __construct(
        private readonly PlatformStatsCalculator $calculator,
    ) {}

    /**
     * @param  string  $date  YYYY-MM-DD, Asia/Tashkent
     * @return bool false for a day that has not started yet
     */
    public function refreshDay(string $date, ?CarbonImmutable $now = null): bool
    {
        $now ??= PlatformStats::now();
        $start = PlatformStats::dayStartOf($date);
        $end = $start->addDay();

        if ($now->lessThan($start)) {
            return false;
        }

        $completeThrough = $now->greaterThanOrEqualTo($end) ? $end : PlatformStats::floorHour($now);
        $computedAt = PlatformStats::column($now);

        DB::transaction(function () use ($date, $start, $end, $completeThrough, $computedAt) {
            if (DB::getDriverName() === 'pgsql') {
                // The scheduled refresh and a backfill of the same day wait for each other; the counting happens under
                // the lock, so the later writer never puts back older numbers
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['platform_stats:' . $date]);
            }

            $hourly = $this->calculator->aggregate($start, $end);
            $daily = [];
            foreach ($hourly as $row) {
                $key = $row->regionId . '|' . $row->channel;
                $daily[$key] = isset($daily[$key]) ? $daily[$key]->plus($row, null) : $row->withHour(null);
            }

            DB::table('platform_hourly_stats')->where('date', $date)->delete();
            DB::table('platform_daily_stats')->where('date', $date)->delete();

            foreach (array_chunk(array_map(fn (StatsRow $row) => $row->toRecord($computedAt), $hourly), 500) as $chunk) {
                DB::table('platform_hourly_stats')->insert($chunk);
            }
            foreach (array_chunk(array_map(fn (StatsRow $row) => $row->toRecord($computedAt), array_values($daily)), 500) as $chunk) {
                DB::table('platform_daily_stats')->insert($chunk);
            }

            DB::table('platform_stats_days')->upsert(
                [['date' => $date, 'complete_through' => PlatformStats::column($completeThrough), 'computed_at' => $computedAt]],
                ['date'],
                ['complete_through', 'computed_at'],
            );
        });

        return true;
    }

    /**
     * Today and yesterday (Asia/Tashkent): what the 5-minute run keeps fresh. Yesterday is included so its last hour
     * is final soon after midnight and late changes of the evening are seen.
     *
     * @param  int  $days  how many local days back from today, today included
     * @return list<string> the days refreshed
     */
    public function refreshRecent(int $days = 2, ?CarbonImmutable $now = null): array
    {
        $now ??= PlatformStats::now();
        $today = PlatformStats::dayStart($now);
        $refreshed = [];

        for ($i = 0; $i < max(1, $days); $i++) {
            $date = $today->subDays($i)->format('Y-m-d');
            if ($this->refreshDay($date, $now)) {
                $refreshed[] = $date;
            }
        }

        return $refreshed;
    }
}
