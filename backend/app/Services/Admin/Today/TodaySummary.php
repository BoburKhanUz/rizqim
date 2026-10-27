<?php

namespace App\Services\Admin\Today;

use App\Enums\BranchStatus;
use App\Enums\OrderChannel;
use App\Enums\StoreStatus;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-13: GET /admin/today. Today so far (Asia/Tashkent) against yesterday and the same weekday a week ago, both up to
 * the same time of day and as whole days; the channel split and the regions. Read from the aggregate tables
 * (PlatformStatsRefresher); only what their last refresh did not cover yet (normally the current hour) is counted
 * live (PlatformStatsCalculator), so the numbers are always up to the moment and a stopped job only makes the page
 * slower, never wrong.
 */
class TodaySummary
{
    public function __construct(
        private readonly PlatformStatsCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?CarbonImmutable $now = null): array
    {
        $now = ($now ?? PlatformStats::now())->setTimezone(PlatformStats::TIMEZONE);
        $today = PlatformStats::dayStart($now);
        $elapsed = $now->getTimestamp() - $today->getTimestamp();
        $yesterday = $today->subDay();
        $lastWeek = $today->subWeek();

        $days = $this->dayRecords([$today, $yesterday, $lastWeek]);

        // Today: the whole local day so far (nothing exists after now); comparisons up to the same time of day
        $todayRows = $this->rows($today, $today->addDay(), $days);
        $periods = [
            'today' => $this->totals($todayRows),
            'yesterday_same_time' => $this->totals($this->rows($yesterday, $yesterday->addSeconds($elapsed), $days)),
            'yesterday' => $this->totals($this->rows($yesterday, $yesterday->addDay(), $days)),
            'last_week_same_time' => $this->totals($this->rows($lastWeek, $lastWeek->addSeconds($elapsed), $days)),
            'last_week' => $this->totals($this->rows($lastWeek, $lastWeek->addDay(), $days)),
        ];

        $todayRecord = $days[$today->format('Y-m-d')] ?? null;

        return [
            'date' => $today->format('Y-m-d'),
            'timezone' => PlatformStats::TIMEZONE,
            'generated_at' => $now->toIso8601String(),
            'stats_computed_at' => $todayRecord ? PlatformStats::fromColumn($todayRecord->computed_at)->setTimezone(PlatformStats::TIMEZONE)->toIso8601String() : null,
            'live_from' => $this->liveFrom($today, $now, $days)->toIso8601String(),
            'kpis' => [
                'gmv' => $this->kpi($periods, 'gmv'),
                'orders' => $this->kpi($periods, 'orders'),
                'commission' => $this->kpi($periods, 'commission'),
                'subscription_revenue' => $this->kpi($periods, 'subscription'),
                'canceled_orders' => $this->kpi($periods, 'canceled'),
            ],
            'channels' => $this->channels($todayRows),
            'regions' => $this->regions($todayRows),
        ];
    }

    /**
     * Rows for [$from, $to) inside one local day: complete hours from the tables, the rest live.
     *
     * @param  array<string, object>  $days  platform_stats_days rows by date
     * @return list<StatsRow>
     */
    public function rows(CarbonImmutable $from, CarbonImmutable $to, array $days): array
    {
        $dayStart = PlatformStats::dayStart($from);
        $dayEnd = $dayStart->addDay();
        $to = $to->min($dayEnd);
        $date = $dayStart->format('Y-m-d');
        $record = $days[$date] ?? null;
        $completeThrough = $record ? PlatformStats::fromColumn($record->complete_through) : $dayStart;

        // Stored rows cover whole hours that were final at the last refresh
        $storedEnd = $completeThrough->min(PlatformStats::floorHour($to))->max($from);
        $rows = [];

        if ($storedEnd->greaterThan($from)) {
            if ($from->equalTo($dayStart) && $storedEnd->greaterThanOrEqualTo($dayEnd)) {
                $records = DB::table('platform_daily_stats')->where('date', $date)->get();
            } else {
                $records = DB::table('platform_hourly_stats')
                    ->where('date', $date)
                    ->where('hour', '>=', $this->hourIndex($from, $dayStart))
                    ->where('hour', '<', $this->hourIndex($storedEnd, $dayStart))
                    ->get();
            }
            foreach ($records as $record) {
                $rows[] = StatsRow::fromRecord($record);
            }
        }

        if ($storedEnd->lessThan($to)) {
            array_push($rows, ...$this->calculator->aggregate($storedEnd, $to));
        }

        return $rows;
    }

    /**
     * @param  list<CarbonImmutable>  $dayStarts
     * @return array<string, object>
     */
    private function dayRecords(array $dayStarts): array
    {
        $dates = array_map(fn (CarbonImmutable $day) => $day->format('Y-m-d'), $dayStarts);

        return DB::table('platform_stats_days')->whereIn('date', $dates)->get()
            ->keyBy(fn (object $record) => substr((string) $record->date, 0, 10))
            ->all();
    }

    /**
     * @param  array<string, object>  $days
     */
    private function liveFrom(CarbonImmutable $today, CarbonImmutable $now, array $days): CarbonImmutable
    {
        $record = $days[$today->format('Y-m-d')] ?? null;
        $completeThrough = $record ? PlatformStats::fromColumn($record->complete_through) : $today;

        return $completeThrough->min(PlatformStats::floorHour($now))->max($today)->setTimezone(PlatformStats::TIMEZONE);
    }

    /**
     * Hours since local midnight (23 for 23:00, 24 for the next midnight); a day is 24 hours in Asia/Tashkent.
     */
    private function hourIndex(CarbonImmutable $at, CarbonImmutable $dayStart): int
    {
        return intdiv($at->getTimestamp() - $dayStart->getTimestamp(), 3600);
    }

    /**
     * Marketplace totals plus subscription revenue.
     *
     * @param  list<StatsRow>  $rows
     * @return array{orders: int, gmv: Money, commission: Money, canceled: int, subscription: Money}
     */
    private function totals(array $rows): array
    {
        $totals = ['orders' => 0, 'gmv' => Money::zero(), 'commission' => Money::zero(), 'canceled' => 0, 'subscription' => Money::zero()];

        foreach ($rows as $row) {
            $totals['subscription'] = $totals['subscription']->plus($row->subscription);
            if (!PlatformStats::isMarketplace($row->channel)) {
                continue;
            }
            $totals['orders'] += $row->orders;
            $totals['gmv'] = $totals['gmv']->plus($row->gmv);
            $totals['commission'] = $totals['commission']->plus($row->commission);
            $totals['canceled'] += $row->canceled;
        }

        return $totals;
    }

    /**
     * @param  array<string, array{orders: int, gmv: Money, commission: Money, canceled: int, subscription: Money}>  $periods
     * @return array<string, mixed>
     */
    private function kpi(array $periods, string $metric): array
    {
        $value = fn (string $period) => $this->present($periods[$period][$metric]);

        return [
            'value' => $value('today'),
            'yesterday_same_time' => $value('yesterday_same_time'),
            'yesterday' => $value('yesterday'),
            'last_week_same_time' => $value('last_week_same_time'),
            'last_week' => $value('last_week'),
            'vs_yesterday' => $this->change($periods['today'][$metric], $periods['yesterday_same_time'][$metric]),
            'vs_last_week' => $this->change($periods['today'][$metric], $periods['last_week_same_time'][$metric]),
        ];
    }

    private function present(Money|int $value): string|int
    {
        return $value instanceof Money ? $value->toString() : $value;
    }

    /**
     * `up` / `down` / `same`, and by how many percent (one decimal) of the earlier value; null when that was zero.
     *
     * @return array{direction: string, percent: string|null}
     */
    public function change(Money|int $current, Money|int $previous): array
    {
        $now = BigDecimal::of($current instanceof Money ? $current->toString() : $current);
        $before = BigDecimal::of($previous instanceof Money ? $previous->toString() : $previous);
        $direction = match ($now->compareTo($before)) {
            1 => 'up',
            -1 => 'down',
            default => 'same',
        };

        $percent = $before->isZero()
            ? null
            : (string) $now->minus($before)->abs()->multipliedBy(100)->dividedBy($before->abs(), 1, RoundingMode::HalfUp);

        return ['direction' => $direction, 'percent' => $percent];
    }

    /**
     * Every channel today, marketplace ones first (the headline counts only those).
     *
     * @param  list<StatsRow>  $rows
     * @return list<array{channel: string, label: string, marketplace: bool, orders: int, gmv: string}>
     */
    private function channels(array $rows): array
    {
        $byChannel = [];
        foreach (OrderChannel::cases() as $channel) {
            $byChannel[$channel->value] = ['orders' => 0, 'gmv' => Money::zero()];
        }
        foreach ($rows as $row) {
            if ($row->channel === PlatformStats::SUBSCRIPTIONS) {
                continue;
            }
            $byChannel[$row->channel] ??= ['orders' => 0, 'gmv' => Money::zero()];
            $byChannel[$row->channel]['orders'] += $row->orders;
            $byChannel[$row->channel]['gmv'] = $byChannel[$row->channel]['gmv']->plus($row->gmv);
        }

        $result = [];
        foreach ($byChannel as $channel => $totals) {
            $result[] = [
                'channel' => (string) $channel,
                'label' => OrderChannel::tryFrom((string) $channel)?->label() ?? (string) $channel,
                'marketplace' => PlatformStats::isMarketplace((string) $channel),
                'orders' => $totals['orders'],
                'gmv' => $totals['gmv']->toString(),
            ];
        }

        usort($result, fn (array $a, array $b) => [$b['marketplace'], $b['orders']] <=> [$a['marketplace'], $a['orders']]);

        return $result;
    }

    /**
     * Regions with marketplace orders today or active stores, most orders first. Active store: status active, not
     * deleted, with an active branch in the region (counted once per region it has branches in).
     *
     * @param  list<StatsRow>  $rows
     * @return list<array{region_id: int|null, name: string, orders: int, gmv: string, active_stores: int}>
     */
    private function regions(array $rows): array
    {
        $byRegion = [];
        foreach ($rows as $row) {
            if (!PlatformStats::isMarketplace($row->channel)) {
                continue;
            }
            $byRegion[$row->regionId] ??= ['orders' => 0, 'gmv' => Money::zero(), 'active_stores' => 0];
            $byRegion[$row->regionId]['orders'] += $row->orders;
            $byRegion[$row->regionId]['gmv'] = $byRegion[$row->regionId]['gmv']->plus($row->gmv);
        }

        $active = DB::table('branches as b')
            ->join('stores as s', 's.id', '=', 'b.store_id')
            ->where('s.status', StoreStatus::ACTIVE->value)
            ->whereNull('s.deleted_at')
            ->where('b.status', BranchStatus::ACTIVE->value)
            ->where('b.is_active', true)
            ->groupByRaw('1')
            ->selectRaw('COALESCE(b.region_id, ' . PlatformStats::NO_REGION . ') AS region_id, COUNT(DISTINCT b.store_id) AS stores')
            ->get();
        foreach ($active as $record) {
            $byRegion[(int) $record->region_id] ??= ['orders' => 0, 'gmv' => Money::zero(), 'active_stores' => 0];
            $byRegion[(int) $record->region_id]['active_stores'] = (int) $record->stores;
        }

        $names = DB::table('regions')->whereIn('id', array_keys($byRegion))->pluck('name_uz', 'id');

        $result = [];
        foreach ($byRegion as $regionId => $totals) {
            $known = $regionId !== PlatformStats::NO_REGION && isset($names[$regionId]);
            $result[] = [
                'region_id' => $known ? (int) $regionId : null,
                'name' => $known ? (string) $names[$regionId] : "Viloyat ko'rsatilmagan",
                'orders' => $totals['orders'],
                'gmv' => $totals['gmv']->toString(),
                'active_stores' => $totals['active_stores'],
            ];
        }

        // A deleted region's rows fall in with "not set"
        $merged = [];
        foreach ($result as $region) {
            $key = $region['region_id'] ?? 0;
            if (isset($merged[$key])) {
                $merged[$key]['orders'] += $region['orders'];
                $merged[$key]['gmv'] = Money::of($merged[$key]['gmv'])->plus($region['gmv'])->toString();
                $merged[$key]['active_stores'] += $region['active_stores'];
            } else {
                $merged[$key] = $region;
            }
        }
        $result = array_values($merged);

        usort($result, fn (array $a, array $b) => [$b['orders'], $b['active_stores'], $a['name']] <=> [$a['orders'], $a['active_stores'], $b['name']]);

        return $result;
    }
}
