<?php

namespace App\Services\Admin\Orders;

use App\Events\Broadcast\OpsOrderUpdated;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P4-14: every minute (WatchOrderSlaJob) finds the marketplace orders whose running SLA stage is at warning or late and
 * records each crossing once (order_sla_events, unique per order, stage and state); a new crossing is broadcast as
 * ops.order.updated (change `sla`). An order that went from ok straight to late between two runs gets its warning row
 * too, but only the late one is broadcast. Bounded per run; the rest follows on the next run.
 */
class OrderSlaWatcher
{
    public const BATCH = 500;

    public function __construct(
        private readonly AdminOrderQuery $orders,
        private readonly OrderSla $sla,
        private readonly OrderSlaSettings $settings,
    ) {}

    /**
     * @return int the transitions broadcast
     */
    public function run(): int
    {
        $limits = $this->settings->limits();
        $now = now();

        $query = $this->orders->withStageTimes(
            $this->orders->whereSla($this->orders->base(), 'attention', $limits, $now)
        );
        // A stage already recorded late has nothing left to cross (the inserts below decide the rest)
        $query->whereNotExists(fn ($events) => $events->selectRaw('1')->from('order_sla_events as e')
            ->whereColumn('e.order_id', 'orders.id')
            ->where('e.state', OrderSla::LATE)
            ->whereRaw("e.stage = case when orders.status = 'new' then 'accept' when orders.status in ('accepted', 'preparing') then 'prepare' else 'deliver' end"));
        $rows = $query->orderBy('orders.id')->limit(self::BATCH)->get();

        $broadcast = 0;
        foreach ($rows as $order) {
            [$acceptedAt, $enRouteAt] = AdminOrderQuery::stageTimes($order);
            $sla = $this->sla->current($order, $acceptedAt, $enRouteAt, $limits, $now);
            if ($sla === null || $sla['state'] === OrderSla::OK) {
                continue;
            }

            $crossings = $sla['state'] === OrderSla::LATE ? [OrderSla::WARNING, OrderSla::LATE] : [OrderSla::WARNING];
            $new = null;
            foreach ($crossings as $state) {
                if ($this->record($order, $sla['stage'], $state, $sla['limit_minutes'], Carbon::parse($state === OrderSla::LATE ? $sla['due_at'] : $sla['warning_at']))) {
                    $new = $state;
                }
            }

            if ($new === $sla['state']) {
                event(new OpsOrderUpdated($order, OpsOrderUpdated::SLA, [
                    'stage' => $sla['stage'],
                    'state' => $sla['state'],
                    'late_by_minutes' => $sla['late_by_minutes'],
                ]));
                $broadcast++;
            }
        }

        return $broadcast;
    }

    /**
     * Whether this crossing is new (inserted now). The unique key makes concurrent runs record it once.
     */
    private function record(Order $order, string $stage, string $state, int $limit, Carbon $crossedAt): bool
    {
        return DB::table('order_sla_events')->insertOrIgnore([
            'order_id' => $order->id,
            'stage' => $stage,
            'state' => $state,
            'limit_minutes' => $limit,
            'crossed_at' => $crossedAt->setTimezone(config('app.timezone', 'UTC')),
            'created_at' => now(),
        ]) === 1;
    }
}
