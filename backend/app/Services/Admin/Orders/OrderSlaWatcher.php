<?php

namespace App\Services\Admin\Orders;

use App\Events\Broadcast\OpsOrderUpdated;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P4-14: every minute (WatchOrderSlaJob) finds the marketplace orders whose running SLA stage is at warning or late and
 * records each crossing once (order_sla_events, unique per order, stage and state); a new crossing is broadcast as
 * ops.order.updated (change `sla`). An order that went from ok straight to late between two runs gets its warning row
 * too, but only the late one is broadcast. Bounded per run; the rest follows on the next run. Only orders with a
 * crossing still to record are read, so orders waiting in a band already recorded never starve new crossings.
 */
class OrderSlaWatcher
{
    public const BATCH = 500;

    public function __construct(
        private readonly AdminOrderQuery $orders,
        private readonly OrderSla $sla,
        private readonly OrderSlaSettings $settings,
    ) {}

    /** The running stage of an order row, as OrderSla::runningStage names it (only rows whereSla matched). */
    private const STAGE = "case when orders.status = 'new' then 'accept' when orders.status in ('accepted', 'preparing') then 'prepare' else 'deliver' end";

    /**
     * @param  int|null  $batch  at most this many orders (BATCH)
     * @return int the transitions broadcast
     */
    public function run(?int $batch = null): int
    {
        $limits = $this->settings->limits();
        $now = now();

        // Only rows with a crossing still to record: late without its late row, or in the warning band without its
        // warning row. A stage already recorded for the band it is in has nothing to cross, so however many orders
        // wait there, they never fill the batch and push a new crossing out to a later run.
        $notRecorded = fn (string $state) => fn (Builder $query) => $query->whereNotExists(fn ($events) => $events->selectRaw('1')
            ->from('order_sla_events as e')
            ->whereColumn('e.order_id', 'orders.id')
            ->where('e.state', $state)
            ->whereRaw('e.stage = ' . self::STAGE));

        $query = $this->orders->withStageTimes($this->orders->base()->where(function (Builder $query) use ($limits, $now, $notRecorded) {
            $query->where(fn (Builder $query) => $notRecorded(OrderSla::LATE)($this->orders->whereSla($query, OrderSla::LATE, $limits, $now)))
                ->orWhere(fn (Builder $query) => $notRecorded(OrderSla::WARNING)($this->orders->whereSla($query, OrderSla::WARNING, $limits, $now)));
        }));
        $rows = $query->orderBy('orders.id')->limit($batch ?? self::BATCH)->get();

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
