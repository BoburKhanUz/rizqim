<?php

namespace App\Console\Commands;

use App\Services\Admin\Today\PlatformStats;
use App\Services\Admin\Today\PlatformStatsRefresher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * P4-13: fills the Bugun aggregate tables for past days (after the first deploy, or to repair a day by hand). Each
 * day is recomputed from scratch, so running it again is safe.
 */
class BackfillPlatformStatsCommand extends Command
{
    protected $signature = 'admin:stats-backfill
        {--from= : first day, YYYY-MM-DD (Asia/Tashkent); default: --days before today}
        {--to= : last day, YYYY-MM-DD; default: today}
        {--days=35 : how many days back from today when --from is not given}';

    protected $description = 'Recompute the Bugun aggregate tables (platform_daily_stats, platform_hourly_stats) for past days';

    private const MAX_DAYS = 3660;

    public function handle(PlatformStatsRefresher $refresher): int
    {
        $today = PlatformStats::dayStart(PlatformStats::now());

        try {
            $to = $this->option('to') ? $this->day((string) $this->option('to')) : $today;
            $from = $this->option('from')
                ? $this->day((string) $this->option('from'))
                : $today->subDays(max(0, (int) $this->option('days')));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        if ($from->greaterThan($to)) {
            $this->error('--from is after --to.');

            return self::INVALID;
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $this->error('At most ' . self::MAX_DAYS . ' days at once.');

            return self::INVALID;
        }

        $count = 0;
        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if ($refresher->refreshDay($day->format('Y-m-d'))) {
                $count++;
            }
        }

        $this->info("{$count} day(s) recomputed ({$from->format('Y-m-d')} .. {$to->format('Y-m-d')}).");

        return self::SUCCESS;
    }

    private function day(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new \InvalidArgumentException("Not a date (YYYY-MM-DD): {$value}");
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, PlatformStats::TIMEZONE);
        if ($day === null || $day->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("Not a date (YYYY-MM-DD): {$value}");
        }

        return $day;
    }
}
