<?php

namespace App\Services\Finance\Reports;

use App\Enums\CommissionChannel;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P4-23: Moliya → Oylik hisobot. RIZQIM's own revenue for one Asia/Tashkent month (docs/API.md "Moliya"):
 *
 * - commission by channel (orders.commission_channel, P4-21's snapshot; older orders: `unknown`): commission_amount
 *   of the orders delivered in the month (the first `delivered` history row) that are still delivered, less what
 *   partial refunds took back of it (order_ledger_reversals: commission_wallet + commission_debt). A fully refunded
 *   order counts nothing — the same rule as RevenueReportService (P1-23), plus the parts;
 * - subscriptions: the month's subscription invoices that are not void (invoiced, paid of it, still open);
 * - per store: both, largest total first.
 *
 * Amounts are Money; the controller and the Excel export turn them into decimal strings / numbers.
 */
class MonthlyRevenueReport
{
    public const UNKNOWN_CHANNEL = 'unknown';

    public function timezone(): string
    {
        return (string) config('billing.timezone', 'Asia/Tashkent');
    }

    /**
     * @return array{period: string, timezone: string, totals: array{orders: int, gmv: Money, commission: Money, commission_refunded: Money, subscription: Money, total: Money}, channels: list<array{channel: string, label: string, orders: int, gmv: Money, commission: Money, commission_refunded: Money}>, subscriptions: array{invoices: int, invoiced: Money, paid: Money, outstanding: Money}, stores: list<array{store_id: int, store_name: string, orders: int, gmv: Money, commission: Money, commission_by_channel: array<string, Money>, subscription: Money, total: Money}>}
     */
    public function build(string $period): array
    {
        $tz = $this->timezone();
        $appTz = (string) config('app.timezone');
        $start = Carbon::createFromFormat('!Y-m', $period, $tz)->setTimezone($appTz);
        $end = Carbon::createFromFormat('!Y-m', $period, $tz)->addMonthNoOverflow()->setTimezone($appTz);

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
            ->leftJoinSub(
                DB::table('order_ledger_reversals')->groupBy('order_id')->selectRaw(
                    "order_id, SUM(COALESCE((components->>'commission_wallet')::numeric, 0) + COALESCE((components->>'commission_debt')::numeric, 0)) AS reversed"
                ),
                'r',
                'r.order_id',
                '=',
                'o.id'
            )
            ->where('o.status', OrderStatus::DELIVERED->value)
            ->whereNull('o.deleted_at')
            ->whereRaw("{$deliveredAt} >= ?", [$start->format('Y-m-d H:i:s')])
            ->whereRaw("{$deliveredAt} < ?", [$end->format('Y-m-d H:i:s')])
            ->groupBy('o.store_id', 'o.commission_channel')
            ->selectRaw('o.store_id, o.commission_channel AS channel, COUNT(*) AS orders, COALESCE(SUM(o.total_price), 0) AS gmv, '
                . 'COALESCE(SUM(o.commission_amount), 0) AS commission, COALESCE(SUM(r.reversed), 0) AS reversed')
            ->get();

        $channels = [];
        foreach ([...CommissionChannel::values(), self::UNKNOWN_CHANNEL] as $channel) {
            $channels[$channel] = ['orders' => 0, 'gmv' => Money::zero(), 'commission' => Money::zero(), 'commission_refunded' => Money::zero()];
        }
        $stores = [];
        $blank = fn () => ['orders' => 0, 'gmv' => Money::zero(), 'commission' => Money::zero(), 'commission_by_channel' => [], 'subscription' => Money::zero()];

        foreach ($rows as $row) {
            $channel = $row->channel !== null && CommissionChannel::tryFrom((string) $row->channel) ? (string) $row->channel : self::UNKNOWN_CHANNEL;
            $reversed = Money::of((string) $row->reversed);
            $net = Money::of((string) $row->commission)->minus($reversed);

            $channels[$channel]['orders'] += (int) $row->orders;
            $channels[$channel]['gmv'] = $channels[$channel]['gmv']->plus((string) $row->gmv);
            $channels[$channel]['commission'] = $channels[$channel]['commission']->plus($net);
            $channels[$channel]['commission_refunded'] = $channels[$channel]['commission_refunded']->plus($reversed);

            $store = $stores[(int) $row->store_id] ?? $blank();
            $store['orders'] += (int) $row->orders;
            $store['gmv'] = $store['gmv']->plus((string) $row->gmv);
            $store['commission'] = $store['commission']->plus($net);
            $store['commission_by_channel'][$channel] = ($store['commission_by_channel'][$channel] ?? Money::zero())->plus($net);
            $stores[(int) $row->store_id] = $store;
        }

        $invoices = DB::table('subscription_invoices')
            ->where('period', $period)
            ->where('status', '!=', SubscriptionInvoiceStatus::VOID->value)
            ->groupBy('store_id')
            ->selectRaw('store_id, COUNT(*) AS invoices, COALESCE(SUM(amount), 0) AS invoiced, COALESCE(SUM(paid_amount), 0) AS paid')
            ->get();

        $subscriptions = ['invoices' => 0, 'invoiced' => Money::zero(), 'paid' => Money::zero(), 'outstanding' => Money::zero()];
        foreach ($invoices as $row) {
            $subscriptions['invoices'] += (int) $row->invoices;
            $subscriptions['invoiced'] = $subscriptions['invoiced']->plus((string) $row->invoiced);
            $subscriptions['paid'] = $subscriptions['paid']->plus(Money::min((string) $row->paid, (string) $row->invoiced));

            $store = $stores[(int) $row->store_id] ?? $blank();
            $store['subscription'] = $store['subscription']->plus((string) $row->invoiced);
            $stores[(int) $row->store_id] = $store;
        }
        $subscriptions['outstanding'] = Money::max(Money::zero(), $subscriptions['invoiced']->minus($subscriptions['paid']));

        $names = Store::withTrashed()->whereIn('id', array_keys($stores))->pluck('name', 'id');
        $storeRows = [];
        foreach ($stores as $storeId => $store) {
            $storeRows[] = [
                'store_id' => $storeId,
                'store_name' => (string) ($names[$storeId] ?? "#{$storeId}"),
                'orders' => $store['orders'],
                'gmv' => $store['gmv'],
                'commission' => $store['commission'],
                'commission_by_channel' => $store['commission_by_channel'],
                'subscription' => $store['subscription'],
                'total' => $store['commission']->plus($store['subscription']),
            ];
        }
        usort($storeRows, fn (array $a, array $b) => $b['total']->compareTo($a['total']) ?: $a['store_id'] <=> $b['store_id']);

        $channelRows = [];
        foreach ($channels as $channel => $values) {
            $channelRows[] = ['channel' => $channel, 'label' => self::channelLabel($channel)] + $values;
        }

        $commission = Money::sum(...array_column($channelRows, 'commission'));

        return [
            'period' => $period,
            'timezone' => $tz,
            'totals' => [
                'orders' => array_sum(array_column($channelRows, 'orders')),
                'gmv' => Money::sum(...array_column($channelRows, 'gmv')),
                'commission' => $commission,
                'commission_refunded' => Money::sum(...array_column($channelRows, 'commission_refunded')),
                'subscription' => $subscriptions['invoiced'],
                'total' => $commission->plus($subscriptions['invoiced']),
            ],
            'channels' => $channelRows,
            'subscriptions' => $subscriptions,
            'stores' => $storeRows,
        ];
    }

    public static function channelLabel(string $channel): string
    {
        return CommissionChannel::tryFrom($channel)?->label() ?? 'Kanal yozilmagan';
    }
}
