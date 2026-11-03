<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The aggregates behind the Mini App's analytics screen (GET /api/v1/owner/analytics), computed in the database for
 * a store (or one branch): a handful of grouped queries instead of the app paging through every order of the period.
 *
 * Periods are calendar days in the store's time zone (P2-33; Asia/Tashkent by default) and cover the orders CREATED within them (the (store_id, created_at, id)
 * and (branch_id, created_at, id) indexes serve the range; an order is counted on the day it was placed, however
 * late it finishes). Revenue is the total of the period's delivered orders, the figure the screens always showed;
 * refunded orders are not revenue. All money is summed by the database and handled as Money, never as floats.
 */
class OwnerAnalyticsService
{
    public const TIMEZONE = 'Asia/Tashkent';

    /** Rolling periods: the last N calendar days, today included. */
    public const PERIOD_DAYS = ['week' => 7, 'month' => 30, 'year' => 365];

    public const TOP_LIMIT = 10;

    /** How long one store's figures for one period are reused. */
    public const CACHE_SECONDS = 60;

    /**
     * The first and last day of a period, in $timezone (P2-33: the store's, as the reports; Asia/Tashkent by default).
     * `custom` needs both dates (Y-m-d).
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function resolvePeriod(string $period, ?string $from = null, ?string $to = null, string $timezone = self::TIMEZONE): array
    {
        if ($period === 'custom') {
            return [
                'from' => CarbonImmutable::createFromFormat('!Y-m-d', (string) $from, $timezone),
                'to' => CarbonImmutable::createFromFormat('!Y-m-d', (string) $to, $timezone),
            ];
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();

        return ['from' => $today->subDays((self::PERIOD_DAYS[$period] ?? 1) - 1), 'to' => $today];
    }

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(int $storeId, ?int $branchId, string $period, ?string $from = null, ?string $to = null, string $timezone = self::TIMEZONE): array
    {
        $range = $this->resolvePeriod($period, $from, $to, $timezone);
        $first = $range['from'];
        $last = $range['to'];

        // The days (and their time zone) are in the key, so a cached "today" is never served on the next day
        $key = sprintf('owner-analytics:%d:%s:%s:%s:%s', $storeId, $branchId ?? 'all', $first->toDateString(), $last->toDateString(), $timezone);

        return Cache::remember($key, self::CACHE_SECONDS, fn () => $this->compute($storeId, $branchId, $period, $first, $last));
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(int $storeId, ?int $branchId, string $period, CarbonImmutable $first, CarbonImmutable $last): array
    {
        $scope = fn (): Builder => $this->ordersBetween($storeId, $branchId, $first, $last);

        $delivered = OrderStatus::DELIVERED->value;

        // Every figure but the rankings, the times and the daily series comes from these few groups
        [
            'by_status' => $byStatus, 'orders' => $ordersCount, 'revenue' => $revenue,
            'payments' => $payments, 'types' => $types, 'channels' => $channels,
        ] = $this->groupTotals($scope());

        $deliveredCount = $byStatus[$delivered] ?? 0;
        $canceledCount = ($byStatus[OrderStatus::CANCELED->value] ?? 0) + ($byStatus[OrderStatus::REJECTED->value] ?? 0);

        return [
            'period' => [
                'key' => $period,
                'from' => $first->toDateString(),
                'to' => $last->toDateString(),
                'days' => $first->diffInDays($last) + 1,
                'timezone' => $first->getTimezone()->getName(),
                // What "in the period" means: the day the order was placed
                'basis' => 'created_at',
            ],
            'summary' => [
                'orders_count' => $ordersCount,
                'delivered_count' => $deliveredCount,
                'canceled_count' => $canceledCount,
                'refunded_count' => $byStatus[OrderStatus::REFUNDED->value] ?? 0,
                'active_count' => $ordersCount - $deliveredCount - $canceledCount - ($byStatus[OrderStatus::REFUNDED->value] ?? 0),
                'revenue' => $revenue->toFloat(),
                'average_check' => $this->average($revenue, $deliveredCount)->toFloat(),
            ],
            // An object even when empty, so clients can always index it by status
            'by_status' => $byStatus === [] ? new \stdClass() : $byStatus,
            'by_payment_method' => $this->breakdown($payments, 'method'),
            'by_order_type' => $this->breakdown($types, 'type'),
            // P1-21: delivered orders by sales channel (telegram, mobile, pos_hall, ...)
            'by_channel' => $this->breakdown($channels, 'channel'),
            'top_products' => $this->topProducts($scope()),
            'couriers' => $this->couriers($scope()),
            'times' => $this->times($scope()),
            'daily' => $this->daily($scope(), $first, $last),
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * P2-12: the store's (a branch's) orders placed from the first day's start to the end of the last day, the days
     * being calendar days of the dates' own timezone (the store's, here and in SalesReportService).
     */
    public function ordersBetween(int $storeId, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last): Builder
    {
        // [start, end) in UTC, how the orders table stores time
        $start = $first->startOfDay()->utc()->format('Y-m-d H:i:s');
        $end = $last->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s');

        return DB::table('orders')
            ->where('orders.store_id', $storeId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('orders.branch_id', $branchId))
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', $start)
            ->where('orders.created_at', '<', $end);
    }

    /**
     * Order counts by status, and the delivered orders' count and revenue in total and by payment method, order type
     * and channel, from one grouped query.
     *
     * @return array{by_status: array<string, int>, orders: int, revenue: Money, delivered: int, payments: array<string, array{orders: int, revenue: Money}>, types: array<string, array{orders: int, revenue: Money}>, channels: array<string, array{orders: int, revenue: Money}>}
     */
    public function groupTotals(Builder $orders): array
    {
        $groups = $orders
            ->selectRaw('orders.status, orders.order_type, orders.payment_method, orders.channel, COUNT(*) AS orders, SUM(orders.total_price) AS total')
            ->groupBy('orders.status', 'orders.order_type', 'orders.payment_method', 'orders.channel')
            ->get();

        $delivered = OrderStatus::DELIVERED->value;
        $byStatus = [];
        $revenue = Money::zero();
        $ordersCount = 0;
        $payments = [];
        $types = [];
        $channels = []; // P1-21
        foreach ($groups as $group) {
            $status = (string) $group->status;
            $count = (int) $group->orders;
            $ordersCount += $count;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + $count;

            if ($status !== $delivered) {
                continue;
            }

            $total = Money::of($group->total);
            $revenue = $revenue->plus($total);
            $method = (string) ($group->payment_method ?? '');
            $type = (string) ($group->order_type ?? '');
            $payments[$method] ??= ['orders' => 0, 'revenue' => Money::zero()];
            $payments[$method]['orders'] += $count;
            $payments[$method]['revenue'] = $payments[$method]['revenue']->plus($total);
            $types[$type] ??= ['orders' => 0, 'revenue' => Money::zero()];
            $types[$type]['orders'] += $count;
            $types[$type]['revenue'] = $types[$type]['revenue']->plus($total);
            $channel = (string) ($group->channel ?? 'telegram');
            $channels[$channel] ??= ['orders' => 0, 'revenue' => Money::zero()];
            $channels[$channel]['orders'] += $count;
            $channels[$channel]['revenue'] = $channels[$channel]['revenue']->plus($total);
        }

        ksort($byStatus);

        return [
            'by_status' => $byStatus,
            'orders' => $ordersCount,
            'revenue' => $revenue,
            'delivered' => $byStatus[$delivered] ?? 0,
            'payments' => $payments,
            'types' => $types,
            'channels' => $channels,
        ];
    }

    /**
     * P2-12: order counts and delivered revenue per branch (orders without a branch under a null id), most revenue first.
     *
     * @return list<array{branch_id: int|null, name: string|null, orders: int, delivered: int, revenue: Money}>
     */
    public function byBranch(Builder $orders): array
    {
        return $orders
            ->leftJoin('branches', 'branches.id', '=', 'orders.branch_id')
            ->groupBy('orders.branch_id', 'branches.name')
            ->selectRaw(
                'orders.branch_id AS branch_id, branches.name AS name, COUNT(*) AS orders, '
                . 'COUNT(*) FILTER (WHERE orders.status = ?) AS delivered, SUM(orders.total_price) FILTER (WHERE orders.status = ?) AS revenue',
                [OrderStatus::DELIVERED->value, OrderStatus::DELIVERED->value],
            )
            ->get()
            ->map(fn (object $row): array => [
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'name' => $row->name !== null ? (string) $row->name : null,
                'orders' => (int) $row->orders,
                'delivered' => (int) $row->delivered,
                'revenue' => Money::of($row->revenue ?? null),
            ])
            ->sort(fn (array $a, array $b) => $b['revenue']->compareTo($a['revenue']) ?: ($a['branch_id'] ?? PHP_INT_MAX) <=> ($b['branch_id'] ?? PHP_INT_MAX))
            ->values()
            ->all();
    }

    /**
     * The best sellers among the delivered orders, by quantity (or revenue): the name as it was when sold.
     *
     * @return list<array{name: string, quantity: Quantity, revenue: Money, orders: int}>
     */
    public function topProductRows(Builder $orders, int $limit = self::TOP_LIMIT, string $sort = 'quantity'): array
    {
        $order = $sort === 'revenue' ? 'SUM(order_items.total_price) DESC' : 'SUM(order_items.quantity) DESC';

        return $orders
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->whereNull('order_items.deleted_at')
            // P6-31: a combo counts as the combo; its parts' kitchen lines are not sales
            ->whereNull('order_items.combo_parent_id')
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name AS name, SUM(order_items.quantity) AS quantity, SUM(order_items.total_price) AS revenue, COUNT(DISTINCT orders.id) AS orders')
            ->orderByRaw($order)
            ->orderBy('order_items.product_name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'quantity' => Quantity::of((string) $row->quantity),
                'revenue' => Money::of($row->revenue),
                'orders' => (int) $row->orders,
            ])
            ->all();
    }

    /**
     * P6-31: the dishes sold inside combos among the delivered orders, by the name they were sold under: how many went
     * inside combos, how many on their own lines, and both together ("lavash: 120 = 80 in combos + 40 alone"). Voided
     * lines are left out; the most sold first.
     *
     * @return list<array{name: string, in_combos: Quantity, alone: Quantity, total: Quantity}>
     */
    public function comboPartRows(Builder $orders, int $limit = self::TOP_LIMIT): array
    {
        $parts = (clone $orders)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->whereNull('order_items.deleted_at')
            ->whereNotNull('order_items.combo_parent_id')
            ->where('order_items.kitchen_status', '!=', \App\Enums\KitchenItemStatus::VOID->value)
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name AS name, SUM(order_items.quantity) AS quantity')
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->name => Quantity::of((string) $row->quantity)]);
        if ($parts->isEmpty()) {
            return [];
        }

        $alone = (clone $orders)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->whereNull('order_items.deleted_at')
            ->whereNull('order_items.combo_parent_id')
            ->where('order_items.kitchen_status', '!=', \App\Enums\KitchenItemStatus::VOID->value)
            ->whereIn('order_items.product_name', $parts->keys()->all())
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name AS name, SUM(order_items.quantity) AS quantity')
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->name => Quantity::of((string) $row->quantity)]);

        return $parts->map(fn (Quantity $inCombos, string $name) => [
            'name' => $name,
            'in_combos' => $inCombos,
            'alone' => $alone->get($name) ?? Quantity::zero(),
            'total' => $inCombos->plus($alone->get($name) ?? Quantity::zero()),
        ])->sort(fn (array $a, array $b) => $b['total']->compareTo($a['total']) ?: strcmp($a['name'], $b['name']))
            ->take($limit)->values()->all();
    }

    /**
     * One row per calendar day (week from Monday, month) of the period, zeros where nothing was ordered, in the
     * timezone of $first.
     *
     * @return list<array{date: string, orders: int, delivered: int, revenue: Money}>
     */
    public function series(Builder $orders, CarbonImmutable $first, CarbonImmutable $last, string $unit = 'day'): array
    {
        $unit = in_array($unit, ['day', 'week', 'month'], true) ? $unit : 'day';
        $timezone = $first->getTimezone()->getName();
        // GROUP BY 1: the bound timezone makes the SELECT expression's text differ from any GROUP BY copy of it
        $rows = $orders
            ->selectRaw(
                "DATE(DATE_TRUNC('{$unit}', (orders.created_at AT TIME ZONE 'UTC') AT TIME ZONE ?)) AS bucket, COUNT(*) AS orders, "
                . 'COUNT(*) FILTER (WHERE orders.status = ?) AS delivered, SUM(orders.total_price) FILTER (WHERE orders.status = ?) AS revenue',
                [$timezone, OrderStatus::DELIVERED->value, OrderStatus::DELIVERED->value],
            )
            ->groupByRaw('1')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->bucket);

        $start = match ($unit) {
            'week' => $first->startOfWeek(CarbonImmutable::MONDAY),
            'month' => $first->startOfMonth(),
            default => $first,
        };

        $series = [];
        for ($bucket = $start; $bucket->lessThanOrEqualTo($last); $bucket = $unit === 'month' ? $bucket->addMonthNoOverflow() : $bucket->addDays($unit === 'week' ? 7 : 1)) {
            $row = $rows->get($bucket->toDateString());
            $series[] = [
                'date' => $bucket->toDateString(),
                'orders' => (int) ($row->orders ?? 0),
                'delivered' => (int) ($row->delivered ?? 0),
                'revenue' => Money::of($row->revenue ?? null),
            ];
        }

        return $series;
    }

    public function average(Money $total, int $count): Money
    {
        if ($count === 0) {
            return Money::zero();
        }

        return Money::of(BigDecimal::of($total->toString())->dividedBy($count, Money::SCALE, RoundingMode::HalfUp));
    }

    /**
     * @param  array<string, array{orders: int, revenue: Money}>  $buckets
     * @return list<array<string, mixed>>
     */
    private function breakdown(array $buckets, string $label): array
    {
        $rows = [];
        foreach ($buckets as $name => $bucket) {
            $rows[] = [$label => $name, 'orders' => $bucket['orders'], 'revenue' => $bucket['revenue']];
        }
        usort($rows, fn (array $a, array $b) => $b['revenue']->compareTo($a['revenue']) ?: $b['orders'] <=> $a['orders']);

        return array_map(fn (array $row) => [...$row, 'revenue' => $row['revenue']->toFloat()], $rows);
    }

    /**
     * The best sellers among the delivered orders, by quantity: the name as it was when sold (the items keep it).
     *
     * @return list<array{name: string, quantity: int|float, revenue: float}>
     */
    private function topProducts(Builder $orders): array
    {
        return array_map(fn (array $row): array => [
            'name' => $row['name'],
            // Whole numbers stay integers (weighed products can be fractional)
            'quantity' => $row['quantity']->toNumber(),
            'revenue' => $row['revenue']->toFloat(),
        ], $this->topProductRows($orders));
    }

    /**
     * The couriers with the most delivered orders.
     *
     * @return list<array{id: int, name: string, delivered_count: int}>
     */
    private function couriers(Builder $orders): array
    {
        return $orders
            ->join('users', 'users.id', '=', 'orders.courier_id')
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->groupBy('orders.courier_id', 'users.name')
            ->selectRaw('orders.courier_id AS id, users.name AS name, COUNT(*) AS delivered_count')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('users.name')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'delivered_count' => (int) $row->delivered_count,
            ])
            ->all();
    }

    /**
     * Average kitchen time (accepted → preparing) and delivery time (out with the courier → delivered, delivery
     * orders only), in seconds, from the status history; null where no order has both ends.
     *
     * @return array{average_prep_seconds: int|null, average_delivery_seconds: int|null}
     */
    private function times(Builder $orders): array
    {
        // on_the_way (what couriers set) and delivery (the legacy name) are two names for "out with the courier";
        // a delivery order that never had one counts from the moment it started preparing
        $perOrder = $orders
            ->join('order_status_histories AS h', 'h.order_id', '=', 'orders.id')
            ->groupBy('orders.id', 'orders.status', 'orders.order_type')
            ->selectRaw(<<<'SQL'
                orders.status AS status,
                orders.order_type AS order_type,
                MAX(h.created_at) FILTER (WHERE h.status = 'accepted') AS accepted_at,
                MAX(h.created_at) FILTER (WHERE h.status = 'preparing') AS preparing_at,
                MIN(h.created_at) FILTER (WHERE h.status IN ('on_the_way', 'delivery')) AS en_route_at,
                MAX(h.created_at) FILTER (WHERE h.status = 'delivered') AS delivered_at
                SQL);

        $row = DB::query()
            ->fromSub($perOrder, 't')
            ->selectRaw(<<<'SQL'
                AVG(EXTRACT(EPOCH FROM (preparing_at - accepted_at)))
                    FILTER (WHERE accepted_at IS NOT NULL AND preparing_at > accepted_at) AS prep,
                AVG(EXTRACT(EPOCH FROM (delivered_at - COALESCE(en_route_at, preparing_at))))
                    FILTER (WHERE status = 'delivered' AND order_type = 'delivery' AND delivered_at > COALESCE(en_route_at, preparing_at)) AS delivery
                SQL)
            ->first();

        return [
            'average_prep_seconds' => isset($row->prep) ? (int) round((float) $row->prep) : null,
            'average_delivery_seconds' => isset($row->delivery) ? (int) round((float) $row->delivery) : null,
        ];
    }

    /**
     * One row per calendar day of the period (days without orders are zeros), for the charts.
     *
     * @return list<array{date: string, orders: int, delivered: int, revenue: float}>
     */
    private function daily(Builder $orders, CarbonImmutable $first, CarbonImmutable $last): array
    {
        return array_map(fn (array $row): array => [...$row, 'revenue' => $row['revenue']->toFloat()], $this->series($orders, $first, $last));
    }
}
