<?php

namespace App\Domain\Pos\Kitchen;

use App\Domain\Pos\Setup\PosSettings;
use App\Enums\KitchenStationKind;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\KitchenStation;
use App\Models\Store;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * "Tayyorlash vaqti" (P6-33, B6): how long the kitchen takes, from the real timestamps of the lines, per station, per
 * dish and per hour of the day, for the business days from..to of a branch (Asia/Tashkent or the store's zone, the
 * branch's day start; the moments are compared in UTC — the BatchYieldReport fix of wave 2).
 *
 * A line counts when it reached the kitchen inside the period and was bumped ready (ready_at), voided lines never:
 *
 *   started   sent_at (a POS line); an online line (Telegram, mobile) has none: the order's acceptance (else its creation),
 *             for a later pickup its kitchen time if later (orders.kitchen_fire_at, KitchenTargets::orderStart)
 *   seconds   ready_at − started (never below 0). A line recalled and bumped again counts once, with its last ready_at;
 *             lines a cashier marked ready (POST /pos/orders/{uuid}/ready) or an order closed with lines still cooking
 *             count with that moment
 *   station   the line's own (POS); an online line's: the one its dish routes to now; null: no station (the default
 *             printer)
 *   target    the dish's own (products.kds_target_seconds) ?? the station's (kitchen_stations.target_seconds) — the
 *             targets as they are now; a line without one is counted but not in `with_target`
 *   hour      the local hour (0–23) the line started in
 *   combo     a combo is counted as its parts (each its own kitchen line on its own station, P6-31), never as the combo
 *             line, which only rolls them up
 *
 * Each group: `count`, `median_seconds` and `p90_seconds` (linear interpolation between the closest ranks, like
 * PostgreSQL's percentile_cont, rounded to whole seconds; null without lines), `with_target`, `over_target` (seconds >
 * target) and `over_target_share` (percent of `with_target`, one decimal, a string; null when no line has a target).
 *
 * `orders`: per order that had a counted line, from its first line started to its kitchen done (the expo's
 * assembled_at when it was assembled, else its last ready_at) — the "90% of orders in 9 minutes" figure. `expo`: the
 * orders assembled in the period on the expo station, from their last line ready to assembled_at, against the expo
 * station's target; null when the branch has no expo station or a non-expo station is filtered. `station_id` filters
 * the lines (summary, stations, products, hours); `orders` always counts every station.
 */
final class PrepTimeReport
{
    /** At most this many business days in one report. */
    public const MAX_DAYS = 31;

    /** A group's figures (summary, stations, products, hours, orders, expo). */
    public const STATS = 'count: int, median_seconds: int|null, p90_seconds: int|null, with_target: int, over_target: int, over_target_share: string|null';

    public const SHAPE = 'array{branch_id: int, from: string, to: string, timezone: string, day_starts_at: string, station_id: int|null, summary: array{' . self::STATS . '}, orders: array{' . self::STATS . '}, stations: list<array{station_id: int|null, name: string, kind: string|null, target_seconds: int|null, ' . self::STATS . '}>, products: list<array{product_id: int|null, name: string, ' . self::STATS . '}>, hours: list<array{hour: int, ' . self::STATS . '}>, expo: array{station_id: int, name: string, target_seconds: int|null, ' . self::STATS . '}|null}';

    /**
     * @return array<string, mixed>
     */
    public function build(Branch $branch, string $from, string $to, ?int $stationId = null): array
    {
        $store = Store::find($branch->store_id);
        $zone = StoreTimezone::of($store);
        $dayStart = PosSettings::timeOf($branch->getAttribute('business_day_starts_at'));
        // The local day starts as UTC moments (the columns are UTC)
        $start = CarbonImmutable::parse("{$from} {$dayStart}", $zone)->utc();
        $end = CarbonImmutable::parse("{$to} {$dayStart}", $zone)->addDay()->utc();

        $stations = KitchenStation::query()->where('branch_id', $branch->id)->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
        $targets = new KitchenTargets();
        $targets->prefetchVariants(array_map('intval', $this->lines($branch, $start, $end)->whereNotNull('order_items.product_variant_id')
            ->distinct()->pluck('order_items.product_variant_id')->all()));

        /** @var array<string, array<int|string, list<int>>> $seconds kind => key => seconds */
        $seconds = ['summary' => [], 'stations' => [], 'products' => [], 'hours' => []];
        /** @var array<string, array<int|string, array{with: int, over: int}>> $over */
        $over = ['summary' => [], 'stations' => [], 'products' => [], 'hours' => []];
        $names = [];
        /** @var array<int, array{start: int, done: int}> $orders */
        $orders = [];

        $parse = fn ($value) => $value !== null ? CarbonImmutable::parse($value, 'UTC') : null;
        foreach ($this->lines($branch, $start, $end)->orderBy('order_items.id')->select([
            'order_items.id', 'order_items.order_id', 'order_items.product_variant_id', 'order_items.product_name',
            'order_items.station_id', 'order_items.sent_at', 'order_items.ready_at', 'order_items.fire_mode',
            'orders.created_at as order_created_at', 'orders.assembled_at as order_assembled_at', 'orders.pickup_at as order_pickup_at', 'orders.kitchen_fire_at as order_fire_at',
        ])->selectSub(fn ($history) => $history->from('order_status_histories')
            ->selectRaw('max(order_status_histories.created_at)')
            ->whereColumn('order_status_histories.order_id', 'orders.id')
            ->where('order_status_histories.status', OrderStatus::ACCEPTED->value), 'order_accepted_at')
            ->cursor() as $row) {
            // An online line from its acceptance; one for a later pickup from its kitchen time (KitchenTargets::orderStart)
            $started = $row->sent_at ?? ($row->fire_mode === null
                ? KitchenTargets::orderStart($parse($row->order_created_at), $parse($row->order_accepted_at), $row->order_pickup_at !== null ? $parse($row->order_fire_at) : null)
                : null);
            if ($started === null) {
                continue;
            }
            $begin = CarbonImmutable::parse($started, 'UTC');
            if ($begin->lt($start) || $begin->gte($end)) {
                continue;
            }
            $ready = CarbonImmutable::parse($row->ready_at, 'UTC');
            $took = max(0, (int) $begin->diffInSeconds($ready, false));

            // The order's kitchen time, whatever the station filter
            $orderId = (int) $row->order_id;
            $done = $row->order_assembled_at !== null ? max($ready->getTimestamp(), CarbonImmutable::parse($row->order_assembled_at, 'UTC')->getTimestamp()) : $ready->getTimestamp();
            $orders[$orderId] = [
                'start' => min($orders[$orderId]['start'] ?? PHP_INT_MAX, $begin->getTimestamp()),
                'done' => max($orders[$orderId]['done'] ?? 0, $done),
            ];

            $variantId = $row->product_variant_id !== null ? (int) $row->product_variant_id : null;
            $station = $row->station_id !== null ? (int) $row->station_id
                : ($row->fire_mode === null && $variantId !== null ? $targets->routedStation((int) $branch->id, $variantId) : null);
            if ($stationId !== null && $station !== $stationId) {
                continue;
            }
            $target = $targets->targetFor($variantId, $station);
            $product = $targets->productOf($variantId);
            $productKey = $product ?? 0;
            $names[$productKey] ??= $targets->productNameOf($variantId) ?? (string) $row->product_name;

            foreach (['summary' => 0, 'stations' => $station ?? 0, 'products' => $productKey, 'hours' => (int) $begin->setTimezone($zone)->format('G')] as $kind => $key) {
                $seconds[$kind][$key][] = $took;
                $over[$kind][$key] ??= ['with' => 0, 'over' => 0];
                if ($target !== null) {
                    $over[$kind][$key]['with']++;
                    if ($took > $target) {
                        $over[$kind][$key]['over']++;
                    }
                }
            }
        }

        $expoStation = $stations->first(fn (KitchenStation $station) => $station->kind === KitchenStationKind::EXPO && $station->is_active)
            ?? $stations->first(fn (KitchenStation $station) => $station->kind === KitchenStationKind::EXPO);
        $filtered = $stationId !== null ? $stations->get($stationId) : null;

        return [
            'branch_id' => (int) $branch->id,
            'from' => $from,
            'to' => $to,
            'timezone' => $zone,
            'day_starts_at' => $dayStart,
            'station_id' => $stationId,
            'summary' => self::stats($seconds['summary'][0] ?? [], $over['summary'][0] ?? null),
            'orders' => self::stats(array_map(fn (array $order) => max(0, $order['done'] - $order['start']), array_values($orders)), null),
            'stations' => $this->stationRows($stations, $seconds['stations'], $over['stations']),
            'products' => $this->productRows($names, $seconds['products'], $over['products']),
            'hours' => array_map(fn (int $hour) => ['hour' => $hour] + self::stats($seconds['hours'][$hour] ?? [], $over['hours'][$hour] ?? null), range(0, 23)),
            'expo' => $expoStation !== null && ($filtered === null || $filtered->id === $expoStation->id)
                ? $this->expo($branch, $expoStation, $start, $end)
                : null,
        ];
    }

    /**
     * The lines of the branch that may count: not voided, bumped ready, and either sent inside the period (POS) or an
     * online line of an order created from a day before it (accepted inside it; the exact moment is checked per row).
     */
    private function lines(Branch $branch, CarbonImmutable $start, CarbonImmutable $end): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.branch_id', $branch->id)
            ->whereNull('order_items.deleted_at')
            ->where('order_items.kitchen_status', '!=', 'void')
            ->whereNotNull('order_items.ready_at')
            // P6-31: a combo is cooked as its parts (their kitchen lines count); the combo line only rolls them up
            ->whereNull('order_items.combo_parts')
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('order_items.sent_at', '>=', $start)->where('order_items.sent_at', '<', $end))
                ->orWhere(fn ($query) => $query->whereNull('order_items.sent_at')->whereNull('order_items.fire_mode')
                    ->where('orders.created_at', '>=', $start->subDay())->where('orders.created_at', '<', $end)));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, KitchenStation>  $stations
     * @param  array<int|string, list<int>>  $seconds
     * @param  array<int|string, array{with: int, over: int}>  $over
     * @return list<array<string, mixed>>
     */
    private function stationRows($stations, array $seconds, array $over): array
    {
        $rows = [];
        // In the branch's station order, the lines without a station last
        foreach ($stations as $station) {
            if (isset($seconds[(int) $station->id])) {
                $rows[] = [
                    'station_id' => (int) $station->id,
                    'name' => (string) $station->name,
                    'kind' => $station->kind->value,
                    'target_seconds' => $station->target_seconds !== null ? (int) $station->target_seconds : null,
                ] + self::stats($seconds[(int) $station->id], $over[(int) $station->id] ?? null);
            }
        }
        foreach ($seconds as $key => $list) {
            if ((int) $key === 0 || !$stations->has((int) $key)) {
                $rows[] = ['station_id' => (int) $key === 0 ? null : (int) $key, 'name' => (int) $key === 0 ? 'Sexsiz' : "Sex #{$key}", 'kind' => null, 'target_seconds' => null]
                    + self::stats($list, $over[$key] ?? null);
            }
        }

        return $rows;
    }

    /**
     * Busiest dishes first.
     *
     * @param  array<int, string>  $names
     * @param  array<int|string, list<int>>  $seconds
     * @param  array<int|string, array{with: int, over: int}>  $over
     * @return list<array<string, mixed>>
     */
    private function productRows(array $names, array $seconds, array $over): array
    {
        $rows = [];
        foreach ($seconds as $key => $list) {
            $rows[] = ['product_id' => (int) $key === 0 ? null : (int) $key, 'name' => $names[(int) $key] ?? ''] + self::stats($list, $over[$key] ?? null);
        }
        usort($rows, fn (array $a, array $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return $rows;
    }

    /**
     * The orders the expo assembled in the period: from their last line ready to assembled_at.
     *
     * @return array<string, mixed>
     */
    private function expo(Branch $branch, KitchenStation $station, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $target = $station->target_seconds !== null ? (int) $station->target_seconds : null;
        $list = [];
        $counts = ['with' => 0, 'over' => 0];
        DB::table('orders')
            ->where('orders.branch_id', $branch->id)
            ->where('orders.assembled_at', '>=', $start)
            ->where('orders.assembled_at', '<', $end)
            ->select(['orders.id', 'orders.assembled_at'])
            ->selectSub(fn ($lines) => $lines->from('order_items')->selectRaw('max(order_items.ready_at)')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereNull('order_items.deleted_at')
                ->where('order_items.kitchen_status', '!=', 'void'), 'last_ready_at')
            ->orderBy('orders.id')
            ->get()
            ->each(function (\stdClass $row) use (&$list, &$counts, $target) {
                if ($row->last_ready_at === null) {
                    return;
                }
                $took = max(0, (int) CarbonImmutable::parse($row->last_ready_at, 'UTC')->diffInSeconds(CarbonImmutable::parse($row->assembled_at, 'UTC'), false));
                $list[] = $took;
                if ($target !== null) {
                    $counts['with']++;
                    $counts['over'] += $took > $target ? 1 : 0;
                }
            });

        return ['station_id' => (int) $station->id, 'name' => (string) $station->name, 'target_seconds' => $target] + self::stats($list, $counts);
    }

    /**
     * @param  list<int>  $values
     * @param  array{with: int, over: int}|null  $over
     * @return array{count: int, median_seconds: int|null, p90_seconds: int|null, with_target: int, over_target: int, over_target_share: string|null}
     */
    public static function stats(array $values, ?array $over): array
    {
        sort($values);
        $with = $over['with'] ?? 0;
        $late = $over['over'] ?? 0;

        return [
            'count' => count($values),
            'median_seconds' => self::percentile($values, 0.5),
            'p90_seconds' => self::percentile($values, 0.9),
            'with_target' => $with,
            'over_target' => $late,
            'over_target_share' => $with > 0 ? number_format($late / $with * 100, 1, '.', '') : null,
        ];
    }

    /**
     * percentile_cont over sorted values: linear between the two closest ranks, rounded half up to a whole second.
     *
     * @param  list<int>  $sorted
     */
    public static function percentile(array $sorted, float $fraction): ?int
    {
        $count = count($sorted);
        if ($count === 0) {
            return null;
        }
        $position = $fraction * ($count - 1);
        $low = (int) floor($position);
        $high = (int) ceil($position);
        $value = $sorted[$low] + ($sorted[$high] - $sorted[$low]) * ($position - $low);

        return (int) round($value, 0, PHP_ROUND_HALF_UP);
    }
}
