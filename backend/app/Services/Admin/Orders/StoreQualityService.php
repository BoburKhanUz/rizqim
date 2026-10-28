<?php

namespace App\Services\Admin\Orders;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * P4-14: how well a store handles marketplace orders over the last 30 days (GET /admin/orders/store-quality), one
 * aggregate query over the store's orders and their status history, cached 5 minutes:
 *  - orders: orders that reached the store (not an online order nobody paid for, not one the customer cancelled while
 *    it was still new);
 *  - acceptance_rate: of those, the share the store accepted;
 *  - avg_accept_minutes: checkout → accepted by staff (cash and keshbek orders; online ones are accepted by the payment);
 *  - avg_prep_minutes: accepted → ready;
 *  - cancel_rate: of those, the share cancelled or rejected (by anyone: the store, the team, a no-show);
 *  - rating: the store's rating (stores.rating, review_count) and the ratings given in the period.
 */
class StoreQualityService
{
    public const DAYS = 30;

    private const CACHE_TTL = 300;

    private const LIST_LIMIT = 50;

    /**
     * @return array{period: array{from: string, to: string, days: int}, stores: list<array<string, mixed>>}
     */
    public function report(?int $storeId = null): array
    {
        return Cache::remember('admin:orders:store-quality:' . ($storeId ?? 'all'), self::CACHE_TTL, fn () => $this->build($storeId));
    }

    /**
     * @return array{period: array{from: string, to: string, days: int}, stores: list<array<string, mixed>>}
     */
    private function build(?int $storeId): array
    {
        $to = now();
        $from = $to->copy()->subDays(self::DAYS);
        $channels = AdminOrderQuery::channels();
        $online = OrderSla::onlineMethods();
        $in = fn (array $values) => implode(', ', array_fill(0, max(1, count($values)), '?'));

        $sql = "with o as (
                select orders.id, orders.store_id, orders.status, orders.created_at, orders.payment_method, orders.cancel_reason,
                    (select min(h.created_at) from order_status_histories h where h.order_id = orders.id and h.status = 'accepted') as accepted_at,
                    (select min(h.created_at) from order_status_histories h where h.order_id = orders.id and h.status = 'accepted' and h.user_id is not null) as staff_accepted_at,
                    (select min(h.created_at) from order_status_histories h where h.order_id = orders.id and h.status = 'ready') as ready_at
                from orders
                where orders.deleted_at is null and orders.channel in ({$in($channels)}) and orders.created_at >= ? and orders.created_at < ?"
            . ($storeId !== null ? ' and orders.store_id = ?' : '') . "
            ), r as (
                select * from o
                where not (coalesce(o.cancel_reason, '') = 'payment_timeout' or (o.cancel_reason = 'customer_request' and o.accepted_at is null))
            )
            select r.store_id,
                count(*) as orders,
                count(r.accepted_at) as accepted,
                count(*) filter (where r.status in ('canceled', 'rejected')) as canceled,
                avg(extract(epoch from (r.staff_accepted_at - r.created_at))) filter (where r.staff_accepted_at is not null and r.payment_method not in ({$in($online)})) as accept_seconds,
                avg(extract(epoch from (r.ready_at - r.accepted_at))) filter (where r.ready_at is not null and r.accepted_at is not null and r.ready_at >= r.accepted_at) as prep_seconds
            from r
            group by r.store_id";

        $bindings = [...$channels, $from, $to];
        if ($storeId !== null) {
            $bindings[] = $storeId;
        }
        $bindings = [...$bindings, ...($online !== [] ? $online : [''])];

        $rows = collect(DB::select($sql, $bindings))->keyBy('store_id');
        $ids = $storeId !== null ? [$storeId] : $rows->keys()->map(fn ($id) => (int) $id)->all();

        $stores = Store::query()->whereIn('id', $ids)->get(['id', 'name', 'rating', 'review_count'])->keyBy('id');
        $ratings = DB::table('store_ratings')
            ->whereIn('store_id', $ids)
            ->where('created_at', '>=', $from)
            ->groupBy('store_id')
            ->selectRaw('store_id, count(*) as ratings, avg(rating) as average')
            ->get()
            ->keyBy('store_id');

        $report = [];
        foreach ($ids as $id) {
            $store = $stores->get($id);
            if ($store === null) {
                continue;
            }
            $row = $rows->get($id);
            $orders = (int) ($row->orders ?? 0);
            $accepted = (int) ($row->accepted ?? 0);
            $canceled = (int) ($row->canceled ?? 0);
            $rating = $ratings->get($id);

            $report[] = [
                'store' => ['id' => (int) $store->id, 'name' => (string) $store->name],
                'orders' => $orders,
                'accepted' => $accepted,
                'acceptance_rate' => $orders > 0 ? round($accepted / $orders, 3) : null,
                'avg_accept_minutes' => isset($row->accept_seconds) ? round(((float) $row->accept_seconds) / 60, 1) : null,
                'avg_prep_minutes' => isset($row->prep_seconds) ? round(((float) $row->prep_seconds) / 60, 1) : null,
                'canceled' => $canceled,
                'cancel_rate' => $orders > 0 ? round($canceled / $orders, 3) : null,
                'rating' => (string) $store->rating,
                'review_count' => (int) $store->review_count,
                'ratings_in_period' => (int) ($rating->ratings ?? 0),
                'rating_in_period' => isset($rating->average) ? round((float) $rating->average, 2) : null,
            ];
        }

        // The weakest first: lowest acceptance, then most cancellations
        usort($report, fn (array $a, array $b) => [$a['acceptance_rate'] ?? 1, -($a['cancel_rate'] ?? 0), $a['store']['id']]
            <=> [$b['acceptance_rate'] ?? 1, -($b['cancel_rate'] ?? 0), $b['store']['id']]);

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'days' => self::DAYS],
            'stores' => array_slice($report, 0, self::LIST_LIMIT),
        ];
    }
}
