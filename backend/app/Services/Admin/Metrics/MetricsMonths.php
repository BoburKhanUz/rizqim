<?php

namespace App\Services\Admin\Metrics;

use App\Services\Admin\Today\PlatformStats;
use Carbon\CarbonImmutable;

/**
 * P4-31 Ko'rsatkichlar: the calendar months the metrics are drawn over (Asia/Tashkent, like Bugun and the monthly
 * revenue report). A series over N months has N+1 boundaries: the first moment of each month and, last, "now" (the
 * end of the month in progress), so month k runs from boundary k to boundary k+1.
 */
final class MetricsMonths
{
    public const MIN = 3;

    public const MAX = 24;

    public const DEFAULT = 12;

    /**
     * @return list<CarbonImmutable> $months month starts, oldest first, then $now
     */
    public static function boundaries(int $months, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? PlatformStats::now())->setTimezone(PlatformStats::TIMEZONE);
        $first = $now->startOfMonth()->subMonthsNoOverflow($months - 1);

        $boundaries = [];
        for ($i = 0; $i < $months; $i++) {
            $boundaries[] = $first->addMonthsNoOverflow($i);
        }
        $boundaries[] = $now;

        return $boundaries;
    }

    public static function key(CarbonImmutable $monthStart): string
    {
        return $monthStart->setTimezone(PlatformStats::TIMEZONE)->format('Y-m');
    }

    public static function clamp(int $months): int
    {
        return max(self::MIN, min(self::MAX, $months));
    }
}
