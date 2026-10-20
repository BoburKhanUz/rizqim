<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Support\Money;
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
 * Periods are calendar days in Asia/Tashkent and cover the orders CREATED within them (the (store_id, created_at, id)
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
     * The first and last day (Asia/Tashkent) of a period. `custom` needs both dates (Y-m-d).
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function resolvePeriod(string $period, ?string $from = null, ?string $to = null): array
    {
        if ($period === 'custom') {
            return [
                'from' => CarbonImmutable::createFromFormat('!Y-m-d', (string) $from, self::TIMEZONE),
                'to' => CarbonImmutable::createFromFormat('!Y-m-d', (string) $to, self::TIMEZONE),
            ];
        }

        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        return ['from' => $today->subDays((self::PERIOD_DAYS[$period] ?? 1) - 1), 'to' => $today];
    }

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(int $storeId, ?int $branchId, string $period, ?string $from = null, ?string $to = null): array
    {
        $range = $this->resolvePeriod($period, $from, $to);
        $first = $range['from'];
        $last = $range['to'];

        // The days are in the key, so a cached "today" is never served on the next day
        $key = sprintf('owner-analytics:%d:%s:%s:%s', $storeId, $branchId ?? 'all', $first->toDateString(), $last->toDateString());

        return Cache::remember($key, self::CACHE_SECONDS, fn () => $this->compute($storeId, $branchId, $period, $first, $last));
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(int $storeId, ?int $branchId, string $period, CarbonImmutable $first, CarbonImmutable $last): array
    {
        // [start, end) in UTC, how the orders table stores time
        $start = $first->startOfDay()->utc()->format('Y-m-d H:i:s');
        $end = $last->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s');
        $scope = fn (): Builder => DB::table('orders')
            ->where('orders.store_id', $storeId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('orders.branch_id', $branchId))
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', $start)
            ->where('orders.created_at', '<', $end);

        $delivered = OrderStatus::DELIVERED->value;

        // Every figure but the rankings, the times and the daily series comes from these few groups
        $groups = $scope()
            ->selectRaw('orders.status, orders.order_type, orders.payment_method, orders.channel, COUNT(*) AS orders, SUM(orders.total_price) AS total')
            ->groupBy('orders.status', 'orders.order_type', 'orders.payment_method', 'orders.channel')
            ->get();

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
        $deliveredCount = $byStatus[$delivered] ?? 0;
        $canceledCount = ($byStatus[OrderStatus::CANCELED->value] ?? 0) + ($byStatus[OrderStatus::REJECTED->value] ?? 0);

        return [
            'period' => [
                'key' => $period,
                'from' => $first->toDateString(),
                'to' => $last->toDateString(),
                'days' => $first->diffInDays($last) + 1,
                'timezone' => self::TIMEZONE,
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

    private function average(Money $total, int $count): Money
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
        return $orders
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->whereNull('order_items.deleted_at')
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name AS name, SUM(order_items.quantity) AS quantity, SUM(order_items.total_price) AS revenue')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->orderBy('order_items.product_name')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(function (object $row): array {
                $quantity = (float) $row->quantity;

                return [
                    'name' => (string) $row->name,
                    // Whole numbers stay integers (weighed products can be fractional)
                    'quantity' => floor($quantity) === $quantity ? (int) $quantity : $quantity,
                    'revenue' => Money::of($row->revenue)->toFloat(),
                ];
            })
            ->all();
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
        // The zone is a constant of this class, inlined so the SELECT and GROUP BY expressions are the same text
        $day = sprintf("DATE((orders.created_at AT TIME ZONE 'UTC') AT TIME ZONE '%s')", self::TIMEZONE);
        $rows = $orders
            ->selectRaw(
                "{$day} AS day, COUNT(*) AS orders, COUNT(*) FILTER (WHERE orders.status = ?) AS delivered, "
                . 'SUM(orders.total_price) FILTER (WHERE orders.status = ?) AS revenue',
                [OrderStatus::DELIVERED->value, OrderStatus::DELIVERED->value],
            )
            ->groupByRaw($day)
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->day);

        $series = [];
        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $series[] = [
                'date' => $day->toDateString(),
                'orders' => (int) ($row->orders ?? 0),
                'delivered' => (int) ($row->delivered ?? 0),
                'revenue' => Money::of($row->revenue ?? null)->toFloat(),
            ];
        }

        return $series;
    }
}
