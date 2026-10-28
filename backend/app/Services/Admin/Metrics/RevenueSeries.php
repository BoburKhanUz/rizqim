<?php

namespace App\Services\Admin\Metrics;

use App\Services\Admin\Today\PlatformStats;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-31 Ko'rsatkichlar: GMV, commission and paid subscription invoices per month, summed from the Bugun aggregate
 * (platform_daily_stats, P4-13): the marketplace channels only (Telegram bot, Mini App, mobile), orders as Bugun
 * counts them. No orders are scanned. A day the aggregate job has not computed (platform_stats_days) is missing from
 * the sums: `missing_days` says how many days up to today have no row, `complete` is true when none is missing; fill
 * older months with `php artisan admin:stats-backfill`.
 */
class RevenueSeries
{
    /**
     * @param  list<CarbonImmutable>  $boundaries  MetricsMonths::boundaries()
     * @return list<array<string, mixed>>
     */
    public function months(array $boundaries): array
    {
        $now = end($boundaries);
        $first = $boundaries[0];
        $firstDate = $first->format('Y-m-d');
        $lastDate = $now->format('Y-m-d');

        $sums = DB::table('platform_daily_stats')
            ->whereBetween('date', [$firstDate, $lastDate])
            ->selectRaw("to_char(date, 'YYYY-MM') AS month")
            ->selectRaw('COALESCE(SUM(orders_count) FILTER (WHERE channel IN (' . $this->marketplace() . ')), 0) AS orders')
            ->selectRaw('COALESCE(SUM(gmv) FILTER (WHERE channel IN (' . $this->marketplace() . ')), 0) AS gmv')
            ->selectRaw('COALESCE(SUM(commission) FILTER (WHERE channel IN (' . $this->marketplace() . ')), 0) AS commission')
            ->selectRaw('COALESCE(SUM(subscription_revenue), 0) AS subscription_revenue')
            ->groupByRaw("to_char(date, 'YYYY-MM')")
            ->get()
            ->keyBy('month');

        $computed = DB::table('platform_stats_days')
            ->whereBetween('date', [$firstDate, $lastDate])
            ->selectRaw("to_char(date, 'YYYY-MM') AS month, COUNT(*) AS days")
            ->groupByRaw("to_char(date, 'YYYY-MM')")
            ->pluck('days', 'month');

        $rows = [];
        $last = count($boundaries) - 2;
        for ($i = 0; $i <= $last; $i++) {
            $month = MetricsMonths::key($boundaries[$i]);
            $daysDue = $i === $last ? (int) $now->day : (int) $boundaries[$i]->daysInMonth;
            $have = (int) ($computed[$month] ?? 0);
            $row = $sums->get($month);

            $rows[] = [
                'month' => $month,
                'partial' => $i === $last,
                'orders' => (int) ($row->orders ?? 0),
                'gmv' => Money::of((string) ($row->gmv ?? '0'))->toString(),
                'commission' => Money::of((string) ($row->commission ?? '0'))->toString(),
                'subscription_revenue' => Money::of((string) ($row->subscription_revenue ?? '0'))->toString(),
                'missing_days' => max(0, $daysDue - $have),
                'complete' => $have >= $daysDue,
            ];
        }

        return $rows;
    }

    private function marketplace(): string
    {
        return implode(', ', array_map(fn (string $channel) => "'" . $channel . "'", PlatformStats::marketplaceChannels()));
    }
}
