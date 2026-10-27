<?php

namespace App\Services\Admin\Today;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionInvoiceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-13: the Bugun numbers straight from orders and subscription invoices for a time range, grouped by local hour,
 * region and channel (definitions: PlatformStats). The aggregate job runs it per day; GET /admin/today only for the
 * part of today the job has not covered yet (a narrow range over orders.created_at and subscription_invoices.paid_at,
 * both indexed).
 */
class PlatformStatsCalculator
{
    /**
     * @return list<StatsRow> one per (date, hour, region, channel) with anything in it
     */
    public function aggregate(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($to->lessThanOrEqualTo($from)) {
            return [];
        }

        return [...$this->orders($from, $to), ...$this->invoices($from, $to)];
    }

    /**
     * @return list<StatsRow>
     */
    private function orders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $local = '((o.created_at AT TIME ZONE ?) AT TIME ZONE ?)';
        $zones = [(string) config('app.timezone'), PlatformStats::TIMEZONE];
        $uncounted = $this->list(PlatformStats::uncountedStatuses());
        $canceled = $this->list(PlatformStats::canceledStatuses());
        $counted = "o.status NOT IN ({$uncounted})";
        $online = PlatformStats::onlineMethods();
        if ($online !== []) {
            // An online order nobody has paid yet is not an order of the day (yet)
            $counted .= " AND NOT (o.status = '" . OrderStatus::NEW->value . "' AND o.payment_method IN ({$this->list($online)})"
                . " AND o.payment_status IS DISTINCT FROM '" . PaymentStatus::PAID->value . "')";
        }

        $rows = DB::table('orders as o')
            ->leftJoin('branches as b', 'b.id', '=', 'o.branch_id')
            ->whereNull('o.deleted_at')
            ->where('o.created_at', '>=', PlatformStats::column($from))
            ->where('o.created_at', '<', PlatformStats::column($to))
            ->selectRaw(
                "to_char({$local}, 'YYYY-MM-DD') AS date, CAST(EXTRACT(HOUR FROM {$local}) AS integer) AS hour, "
                . 'COALESCE(b.region_id, ' . PlatformStats::NO_REGION . ') AS region_id, o.channel AS channel, '
                . "COUNT(*) FILTER (WHERE {$counted}) AS orders_count, "
                . "COALESCE(SUM(o.total_price) FILTER (WHERE {$counted}), 0) AS gmv, "
                . "COALESCE(SUM(o.commission_amount) FILTER (WHERE {$counted}), 0) AS commission, "
                . "COUNT(*) FILTER (WHERE o.status IN ({$canceled})) AS canceled_count, "
                . "COUNT(*) FILTER (WHERE o.status = 'refunded') AS refunded_count, "
                . '0 AS invoices_paid_count, 0 AS subscription_revenue',
                [...$zones, ...$zones]
            )
            ->groupByRaw('1, 2, 3, 4')
            ->get();

        return $rows->map(fn (object $row) => StatsRow::fromRecord($row))->values()->all();
    }

    /**
     * @return list<StatsRow>
     */
    private function invoices(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $local = '((i.paid_at AT TIME ZONE ?) AT TIME ZONE ?)';
        $zones = [(string) config('app.timezone'), PlatformStats::TIMEZONE];

        $rows = DB::table('subscription_invoices as i')
            ->where('i.status', SubscriptionInvoiceStatus::PAID->value)
            ->where('i.paid_at', '>=', PlatformStats::column($from))
            ->where('i.paid_at', '<', PlatformStats::column($to))
            ->selectRaw(
                "to_char({$local}, 'YYYY-MM-DD') AS date, CAST(EXTRACT(HOUR FROM {$local}) AS integer) AS hour, "
                . PlatformStats::NO_REGION . " AS region_id, '" . PlatformStats::SUBSCRIPTIONS . "' AS channel, "
                . '0 AS orders_count, 0 AS gmv, 0 AS commission, 0 AS canceled_count, 0 AS refunded_count, '
                . 'COUNT(*) AS invoices_paid_count, COALESCE(SUM(i.amount), 0) AS subscription_revenue',
                [...$zones, ...$zones]
            )
            ->groupByRaw('1, 2')
            ->get();

        return $rows->map(fn (object $row) => StatsRow::fromRecord($row))->values()->all();
    }

    /**
     * @param  list<string>  $values  fixed enum values (never user input)
     */
    private function list(array $values): string
    {
        return implode(', ', array_map(fn (string $value) => "'" . $value . "'", $values));
    }
}
