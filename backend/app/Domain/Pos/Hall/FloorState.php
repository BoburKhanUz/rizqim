<?php

namespace App\Domain\Pos\Hall;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Order;
use App\Services\Order\OrderQueryService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The floor map of a branch (P3-12, R1, docs/POS_SPEC.md §2.4): halls in order, their places with the open orders on
 * them and a state:
 *
 *   free           no open order
 *   reserved_soon  no open order, a booking for it starts within the next hour (TableReservations)
 *   occupied       an open order
 *   overdue        an open order older than the branch's "open room" alert (pos_settings.alerts.open_room_hours, 6)
 *   bill_printed   an open order whose bill was printed (orders.bill_locked_at, P3-18): guests are paying
 *
 * A closed (seasonal) hall and an inactive place are left out unless an order is still open there. Open POS orders
 * without a place (in the hall without a table, takeaway) are listed apart.
 */
class FloorState
{
    public const RESERVED_SOON_MINUTES = 60;

    public const DEFAULT_OVERDUE_HOURS = 6;

    public function __construct(
        protected TableReservations $reservations,
    ) {}

    /**
     * $withContact: the reservation's guest name and phone (P3-26) for staff who work with orders or bookings; others
     * (a cook's kitchen screen) get null in their place.
     *
     * @return array{branch_id: int, generated_at: string, overdue_after_hours: int, halls: list<array<string, mixed>>, untabled_orders: list<array<string, mixed>>}
     */
    public function forBranch(Branch $branch, ?CarbonInterface $now = null, bool $withContact = false): array
    {
        $now ??= now();
        $overdueHours = $this->overdueHours($branch);

        $open = Order::query()
            ->where('branch_id', $branch->id)
            ->whereIn('channel', [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value])
            ->whereNotIn('status', array_map(fn ($status) => $status->value, OrderQueryService::TERMINAL_STATUSES))
            ->with('waiter:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $counts = $this->lineCounts($open->pluck('id')->all());
        $byTable = $open->whereNotNull('table_id')->groupBy('table_id');

        $halls = Hall::where('branch_id', $branch->id)->orderBy('sort_order')->orderBy('id')->get();
        $tables = DiningTable::where('branch_id', $branch->id)->orderBy('sort_order')->orderBy('id')->get()->groupBy('hall_id');

        $tableIds = $tables->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $reserved = $this->reservations->upcoming((int) $branch->id, $tableIds, $now->copy()->subMinutes(30), $now->copy()->addMinutes(self::RESERVED_SOON_MINUTES));
        if (!$withContact) {
            $reserved = array_map(fn (array $reservation) => array_merge($reservation, ['contact_name' => null, 'contact_phone' => null]), $reserved);
        }

        $result = [];
        foreach ($halls as $hall) {
            $places = [];
            foreach ($tables->get($hall->id, collect()) as $table) {
                /** @var Collection<int, Order> $orders */
                $orders = $byTable->get($table->id, collect());
                if (!$table->is_active && $orders->isEmpty()) {
                    continue;
                }

                $places[] = $this->table($table, $orders, $counts, $reserved[(int) $table->id] ?? null, $now, $overdueHours);
            }

            if (!$hall->is_active && collect($places)->every(fn (array $place) => $place['orders'] === [])) {
                continue;
            }

            $result[] = [
                'id' => (int) $hall->id,
                'name' => $hall->name,
                'sort_order' => $hall->sort_order,
                'is_active' => $hall->is_active,
                'tables' => $places,
            ];
        }

        return [
            'branch_id' => (int) $branch->id,
            'generated_at' => $now->toJSON(),
            'overdue_after_hours' => $overdueHours,
            'halls' => $result,
            'untabled_orders' => $open->whereNull('table_id')->values()->map(fn (Order $order) => $this->order($order, $counts, $now, $overdueHours))->all(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<int, array{items: int, unsent: int, ready: int}>  $counts
     * @param  array{booking_uuid: string, starts_at: string, guests: int|null, contact_name: string|null, contact_phone: string|null}|null  $reservation
     * @return array<string, mixed>
     */
    private function table(DiningTable $table, Collection $orders, array $counts, ?array $reservation, CarbonInterface $now, int $overdueHours): array
    {
        $summaries = $orders->values()->map(fn (Order $order) => $this->order($order, $counts, $now, $overdueHours))->all();

        $status = match (true) {
            $summaries === [] => $reservation !== null ? 'reserved_soon' : 'free',
            collect($summaries)->contains(fn (array $order) => $order['bill_printed']) => 'bill_printed',
            collect($summaries)->contains(fn (array $order) => $order['overdue']) => 'overdue',
            default => 'occupied',
        };

        return [
            'id' => (int) $table->id,
            'name' => $table->name,
            'kind' => $table->kind->value,
            'seats' => $table->seats,
            'layout' => ['x' => $table->layout_x, 'y' => $table->layout_y, 'w' => $table->layout_w, 'h' => $table->layout_h],
            'is_active' => $table->is_active,
            'min_spend' => $table->min_spend,
            'min_spend_mode' => $table->min_spend_mode->value,
            'status' => $status,
            'bill_requested' => collect($summaries)->contains(fn (array $order) => $order['bill_requested'] && !$order['bill_printed']),
            'reservation' => $reservation,
            'orders' => $summaries,
        ];
    }

    /**
     * @param  array<int, array{items: int, unsent: int, ready: int}>  $counts
     * @return array<string, mixed>
     */
    private function order(Order $order, array $counts, CarbonInterface $now, int $overdueHours): array
    {
        $opened = $order->created_at;

        return [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'channel' => $order->channel->value,
            'version' => (int) $order->version,
            'opened_at' => $opened?->toJSON(),
            'open_minutes' => $opened !== null ? max(0, (int) $opened->diffInMinutes($now, false)) : 0,
            'overdue' => $opened !== null && $opened->copy()->addHours($overdueHours)->lessThanOrEqualTo($now),
            'bill_printed' => $order->getAttribute('bill_locked_at') !== null,
            // P3-26: a waiter asked for the bill ("Schyot so'raldi"), POST /pos/orders/{uuid}/request-bill
            'bill_requested' => $order->bill_requested_at !== null,
            'bill_requested_at' => $order->bill_requested_at?->toJSON(),
            'guests_count' => $order->guests_count,
            'waiter' => $order->waiter ? ['id' => (int) $order->waiter->id, 'name' => $order->waiter->name] : null,
            'total_price' => (string) $order->total_price,
            'items_count' => $counts[(int) $order->id]['items'] ?? 0,
            'unsent_count' => $counts[(int) $order->id]['unsent'] ?? 0,
            // P3-26: lines the kitchen marked ready and nobody served yet (the waiter's "olib keting")
            'ready_count' => $counts[(int) $order->id]['ready'] ?? 0,
        ];
    }

    /**
     * Lines per order that are not void, those not sent to the kitchen yet, and those ready to serve.
     *
     * @param  list<int>  $orderIds
     * @return array<int, array{items: int, unsent: int, ready: int}>
     */
    private function lineCounts(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->whereNull('deleted_at')
            ->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->groupBy('order_id')
            ->get([
                'order_id',
                DB::raw('count(*) as items'),
                DB::raw('count(*) filter (where sent_at is null and fire_mode is not null) as unsent'),
                DB::raw("count(*) filter (where kitchen_status = '" . KitchenItemStatus::READY->value . "') as ready"),
            ])
            ->mapWithKeys(fn (object $row) => [(int) $row->order_id => ['items' => (int) $row->items, 'unsent' => (int) $row->unsent, 'ready' => (int) $row->ready]])
            ->all();
    }

    private function overdueHours(Branch $branch): int
    {
        $settings = $branch->getAttribute('pos_settings');
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        $hours = is_array($settings) ? ($settings['alerts']['open_room_hours'] ?? null) : null;

        return is_numeric($hours) && (int) $hours > 0 ? (int) $hours : self::DEFAULT_OVERDUE_HOURS;
    }
}
