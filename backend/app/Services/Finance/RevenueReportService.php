<?php

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P1-23: Rizqim's own income by billing month (docs/BILLING.md), in config('billing.timezone'):
 * - commission: commission_amount of the orders delivered in the month that are still delivered (a refunded order
 *   is taken out), the delivery moment being the order's first `delivered` status history row;
 * - subscription: the amounts of the month's subscription invoices that are not void (issued or paid).
 */
class RevenueReportService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
    ) {}

    /**
     * One row per month from $from to $to ("YYYY-MM", both included), oldest first.
     *
     * @return list<array{period: string, commission: Money, subscription: Money, total: Money}>
     */
    public function monthly(string $from, string $to): array
    {
        $periods = $this->periods($from, $to);
        $commission = $this->commissionBy($periods, false);
        $subscription = $this->subscriptionBy($periods, false);

        return array_map(function (string $period) use ($commission, $subscription) {
            $c = $commission[$period] ?? Money::zero();
            $s = $subscription[$period] ?? Money::zero();

            return ['period' => $period, 'commission' => $c, 'subscription' => $s, 'total' => $c->plus($s)];
        }, $periods);
    }

    /**
     * One row per store with any income in the month, largest total first.
     *
     * @return list<array{store_id: int, store_name: string, commission: Money, subscription: Money, total: Money}>
     */
    public function byStore(string $period): array
    {
        $commission = $this->commissionBy([$period], true);
        $subscription = $this->subscriptionBy([$period], true);
        $storeIds = array_unique([...array_keys($commission), ...array_keys($subscription)]);
        $names = Store::withTrashed()->whereIn('id', $storeIds)->pluck('name', 'id');

        $rows = array_map(function (int $storeId) use ($commission, $subscription, $names) {
            $c = $commission[$storeId] ?? Money::zero();
            $s = $subscription[$storeId] ?? Money::zero();

            return ['store_id' => $storeId, 'store_name' => (string) ($names[$storeId] ?? "#{$storeId}"), 'commission' => $c, 'subscription' => $s, 'total' => $c->plus($s)];
        }, $storeIds);

        usort($rows, fn (array $a, array $b) => $b['total']->compareTo($a['total']) ?: $a['store_id'] <=> $b['store_id']);

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function periods(string $from, string $to): array
    {
        $tz = $this->subscriptions->timezone();
        $month = Carbon::createFromFormat('!Y-m', $from, $tz);
        $last = Carbon::createFromFormat('!Y-m', $to, $tz);
        $periods = [];

        while ($month->lessThanOrEqualTo($last) && count($periods) < 120) {
            $periods[] = $month->format('Y-m');
            $month->addMonthNoOverflow();
        }

        return $periods;
    }

    /**
     * Keyed by period, or by store id for a single period.
     *
     * @param  list<string>  $periods
     * @return array<string|int, Money>
     */
    protected function commissionBy(array $periods, bool $byStore): array
    {
        if ($periods === []) {
            return [];
        }

        $tz = $this->subscriptions->timezone();
        $appTz = (string) config('app.timezone');
        $start = Carbon::createFromFormat('!Y-m', $periods[0], $tz)->setTimezone($appTz);
        $end = Carbon::createFromFormat('!Y-m', end($periods), $tz)->addMonthNoOverflow()->setTimezone($appTz);

        // The timestamp columns hold app-timezone (UTC) wall time
        $deliveredAt = 'COALESCE(h.delivered_at, o.updated_at)';
        $rows = DB::table('orders as o')
            ->leftJoinSub(
                DB::table('order_status_histories')->where('status', OrderStatus::DELIVERED->value)
                    ->groupBy('order_id')->selectRaw('order_id, MIN(created_at) AS delivered_at'),
                'h',
                'h.order_id',
                '=',
                'o.id'
            )
            ->where('o.status', OrderStatus::DELIVERED->value)
            ->whereNull('o.deleted_at')
            ->whereRaw("{$deliveredAt} >= ?", [$start->format('Y-m-d H:i:s')])
            ->whereRaw("{$deliveredAt} < ?", [$end->format('Y-m-d H:i:s')])
            ->groupByRaw($byStore ? 'o.store_id' : 'period')
            ->selectRaw(($byStore ? 'o.store_id AS k' : "to_char(({$deliveredAt} AT TIME ZONE ?) AT TIME ZONE ?, 'YYYY-MM') AS period") . ', SUM(o.commission_amount) AS total', $byStore ? [] : [$appTz, $tz])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$byStore ? (int) $row->k : (string) $row->period] = Money::of($row->total);
        }

        return $result;
    }

    /**
     * @param  list<string>  $periods
     * @return array<string|int, Money>
     */
    protected function subscriptionBy(array $periods, bool $byStore): array
    {
        $rows = SubscriptionInvoice::query()
            ->whereIn('period', $periods)
            ->where('status', '!=', SubscriptionInvoiceStatus::VOID->value)
            ->groupBy($byStore ? 'store_id' : 'period')
            ->selectRaw(($byStore ? 'store_id' : 'period') . ' AS k, SUM(amount) AS total')
            ->toBase()
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$byStore ? (int) $row->k : (string) $row->k] = Money::of($row->total);
        }

        return $result;
    }
}
