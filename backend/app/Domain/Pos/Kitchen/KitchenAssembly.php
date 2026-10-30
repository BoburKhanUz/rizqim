<?php

namespace App\Domain\Pos\Kitchen;

use App\Domain\Pos\Contracts\AssemblyTracker;
use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Contracts\PickupTracker;
use App\Domain\Pos\Menu\ComboKitchen;
use App\Domain\Pos\Orders\PickupQueue;
use App\Domain\Pos\Orders\PickupStatus;
use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\KitchenItemStatus;
use App\Enums\KitchenStationKind;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\Broadcast\KitchenOrderAssembledBroadcast;
use App\Models\Branch;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Order\KitchenService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Order\StaffOrderPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-33 (B6, module `kds_expo`, docs/POS_SPEC.md "KDS v2"): the expo (assembly, "yig'ish") station.
 *
 * Which orders: in a branch with the module and an active `expo` station, an order that becomes ready for a customer or
 * a courier (takeaway, delivery, pickup, a counter order eaten in the hall — not a hall order at a table) whose kitchen
 * lines were cooked on two or more stations (a line without a station counts as one more "station": the default
 * printer). Such an order shows on the expo screen (the ticket's `assembly`) with which stations are done; when the
 * last station bumps its last line the order is NOT ready yet: it waits on the expo. The expo bump
 * (POST /staff/orders/{uuid}/assembled) marks it assembled (orders.assembled_at) and makes it ready the way the last
 * bump used to: a takeaway, pickup or delivery order moves to `ready` (OrderStatusService, the customer is told), and
 * the pickup queue calls the number (`queue.ready` → the board and the customer display). An order cooked on one
 * station, a branch without an expo station or without the module: the existing "every line ready → ready" path,
 * unchanged.
 *
 * An assembly is undone (assembled_at cleared) when a line of the order is recalled or a new one bumped ready (the
 * customer added to it): the expo assembles it again. The cashier's "Tayyor" (POST /pos/orders/{uuid}/ready) still
 * overrides the kitchen, expo included. Nothing here ever blocks a payment, a bill or a close.
 *
 * Instances keep what they read (the module, the expo station, the routes) for their own life only: the kitchen
 * display's ticket list uses one per listing, the order core a new one per change.
 */
class KitchenAssembly implements AssemblyTracker
{
    /** The ticket's `assembly` (GET /staff/kitchen/tickets). */
    public const SHAPE = 'array{station_id: int, required: bool, waiting: bool, assembled_at: string|null, stations: list<array{station_id: int|null, name: string, kind: string|null, items_total: int, items_ready: int, done: bool}>}';

    /** @var array<int, int|null> branch id => the active expo station's id (null: none or the module is off) */
    private array $expo = [];

    /** @var array<int, KitchenStation> station id => station */
    private array $stations = [];

    public function __construct(
        private readonly PosModules $modules,
        private readonly AuditLogger $audit,
    ) {}

    // --- AssemblyTracker -----------------------------------------------------------------------------------------

    public function awaitsAssembly(Order $order): bool
    {
        return self::assembledAt($order) === null && $this->requires($order);
    }

    public function kitchenChanged(Order $order, OrderItem $item): void
    {
        if (self::assembledAt($order) === null) {
            return;
        }
        $order->forceFill(['assembled_at' => null, 'assembled_by' => null])->save();
        event(new KitchenOrderAssembledBroadcast($order, false));
    }

    // --- the expo screen -------------------------------------------------------------------------------------------

    /**
     * Whether the order goes through the expo station (it may be assembled already).
     */
    public function requires(Order $order, ?Collection $items = null): bool
    {
        if ($order->branch_id === null || $this->expoStationId((int) $order->branch_id) === null) {
            return false;
        }
        if ($order->order_type === OrderType::DINE_IN && !PickupQueue::isCounterOrder($order)) {
            return false;
        }

        return count($this->stationGroups($order, $items)) >= 2;
    }

    /**
     * The ticket's `assembly` (GET /staff/kitchen/tickets): null for an order that does not go through the expo;
     * else whether it waits, when it was assembled, and each station's progress (the partial view: which are done).
     *
     * @param  Collection<int, OrderItem>  $items  the lines the kitchen sees
     * @return array{station_id: int, required: bool, waiting: bool, assembled_at: string|null, stations: list<array{station_id: int|null, name: string, kind: string|null, items_total: int, items_ready: int, done: bool}>}|null
     */
    public function present(Order $order, Collection $items): ?array
    {
        if (!$this->requires($order, $items)) {
            return null;
        }
        $groups = $this->stationGroups($order, $items);
        $stations = [];
        $allDone = true;
        foreach ($groups as $key => $lines) {
            $total = count($lines);
            $ready = count(array_filter($lines, fn (OrderItem $item) => in_array($item->kitchen_status->value, KitchenItemStatus::doneValues(), true)));
            $station = $key > 0 ? $this->station($key) : null;
            $allDone = $allDone && $ready === $total;
            $stations[] = [
                'station_id' => $key > 0 ? $key : null,
                'name' => $station !== null ? (string) $station->name : 'Umumiy',
                'kind' => $station?->kind->value,
                'items_total' => $total,
                'items_ready' => $ready,
                'done' => $ready === $total,
            ];
        }
        $at = self::assembledAt($order);

        return [
            'station_id' => (int) $this->expoStationId((int) $order->branch_id),
            'required' => true,
            // Every station is done and nobody has assembled it yet: the expo's turn (not once the cashier's "Tayyor"
            // made it ready anyway)
            'waiting' => $at === null && $allDone && $order->status !== OrderStatus::READY
                && !in_array(PickupQueue::statusOf($order), [PickupStatus::READY, PickupStatus::HANDED], true),
            'assembled_at' => $at?->toJSON(),
            'stations' => $stations,
        ];
    }

    /**
     * POST /staff/orders/{uuid}/assembled: the expo bump. Under the order's row lock. 403 module_disabled without the
     * module; 422 `assembly_not_required` (the order does not go through the expo), `kitchen_status` (not in the
     * kitchen), `stations_not_ready` (a line is still cooking). Assembled already: unchanged.
     *
     * @return array{order: Order, changed: bool, statuses: list<OrderStatus>}
     *
     * @throws PosOrderException
     */
    public function assemble(Order $order, User $actor): array
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $branch = $order->branch_id !== null ? Branch::find($order->branch_id) : null;
            if ($branch !== null) {
                $this->modules->assertEnabled($branch, PosModule::KDS_EXPO);
            }
            if (!$this->requires($order)) {
                throw new PosOrderException(
                    "Bu buyurtma yig'ish sexidan o'tmaydi: u bitta sexda tayyorlanadi yoki filialda yig'ish sexi yo'q.",
                    422,
                    'assembly_not_required',
                );
            }
            if (self::assembledAt($order) !== null) {
                return ['order' => $order, 'changed' => false, 'statuses' => []];
            }

            $tracker = app()->bound(PickupTracker::class) ? app(PickupTracker::class) : null;
            $closedPickup = $order->status === OrderStatus::DELIVERED && $tracker !== null && $tracker->keepsKitchen($order);
            if (!$closedPickup && !in_array($order->status, KitchenService::LINE_STATUSES, true)) {
                throw new PosOrderException("Bu buyurtma oshxonada emas: uni yig'ib bo'lmaydi.", 422, 'kitchen_status');
            }
            // Like the last bump: a line still cooking, or on the order and not sent yet, keeps it from being ready
            if (OrderItem::where('order_id', $order->id)->whereIn('kitchen_status', KitchenItemStatus::cookingValues())->exists()) {
                throw new PosOrderException("Hamma sexlar hali tayyor emas: yig'ishdan oldin ularni kuting.", 422, 'stations_not_ready');
            }

            $order->forceFill(['assembled_at' => now(), 'assembled_by' => $actor->id])->save();

            // The last bump's step, now: a takeaway, pickup or delivery order is ready (a hall order at the counter
            // and a counter order paid first keep their status; their number is called by the pickup queue)
            $statuses = [];
            if ($order->order_type !== OrderType::DINE_IN && !$closedPickup
                && in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING], true)) {
                try {
                    if ($order->status === OrderStatus::ACCEPTED) {
                        $order = app(OrderStatusService::class)->transition($order, OrderStatus::PREPARING, $actor, ['notes' => "Yig'ish: {$actor->name}"])->order;
                    }
                    $result = app(OrderStatusService::class)->transition($order, OrderStatus::READY, $actor, [
                        'notes' => "Yig'ildi, buyurtma tayyor: {$actor->name}",
                    ]);
                } catch (OrderTransitionException $e) {
                    throw new PosOrderException($e->getMessage(), $e->status, 'status');
                }
                $order = $result->order;
                if (!$result->quiet) {
                    $statuses[] = OrderStatus::READY;
                }
            }

            $tracker?->kitchenChanged($order->refresh());
            $this->audit->record('kds.order_assembled', $order, [], ['assembled_at' => self::assembledAt($order)?->toJSON()], [
                'short_code' => $order->short_code,
                'queue_number' => $order->queue_number,
            ], ['actor' => $actor]);
            event(new KitchenOrderAssembledBroadcast($order, true));

            return ['order' => $order, 'changed' => true, 'statuses' => $statuses];
        });
    }

    // --- internals -------------------------------------------------------------------------------------------------

    public static function assembledAt(Order $order): ?Carbon
    {
        $value = $order->getAttribute('assembled_at');
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value, 'UTC');
    }

    /**
     * The branch's active expo station when the module is on; null otherwise.
     */
    public function expoStationId(int $branchId): ?int
    {
        if (array_key_exists($branchId, $this->expo)) {
            return $this->expo[$branchId];
        }

        $station = KitchenStation::query()->where('branch_id', $branchId)->where('kind', KitchenStationKind::EXPO->value)
            ->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        $branch = $station !== null ? Branch::find($branchId) : null;

        return $this->expo[$branchId] = $branch !== null && $this->modules->enabled($branch, PosModule::KDS_EXPO) ? (int) $station->id : null;
    }

    /**
     * The order's kitchen lines (not voided, seen by the kitchen) by station: the line's own station, an online line's
     * routed one, 0 for a line without a station (listed last).
     *
     * @param  Collection<int, OrderItem>|null  $items
     * @return array<int, list<OrderItem>>
     */
    private function stationGroups(Order $order, ?Collection $items): array
    {
        // P6-31: the kitchen's lines: a combo line is cooked as its parts, each on its own station
        $items ??= ComboKitchen::forKitchen(OrderItem::where('order_id', $order->id)->orderBy('id')->get());
        $resolver = app()->bound(KitchenStationResolver::class) ? app(KitchenStationResolver::class) : null;
        $groups = [];
        foreach ($items as $item) {
            if ($item->isVoid() || !StaffOrderPresenter::kitchenSees($item)) {
                continue;
            }
            $item->setRelation('order', $order);
            $stationId = $item->station_id ?? ($item->fire_mode === null ? $resolver?->stationIdFor($item) : null);
            $groups[(int) ($stationId ?? 0)][] = $item;
        }
        // In the branch's station order, the lines without a station last
        uksort($groups, function (int $a, int $b) {
            $order = fn (int $id) => $id === 0 ? [PHP_INT_MAX, 0] : [(int) ($this->station($id)->sort_order ?? PHP_INT_MAX - 1), $id];

            return $order($a) <=> $order($b);
        });

        return $groups;
    }

    private function station(int $id): ?KitchenStation
    {
        if (!isset($this->stations[$id])) {
            $station = KitchenStation::find($id);
            if ($station === null) {
                return null;
            }
            $this->stations[$id] = $station;
        }

        return $this->stations[$id];
    }
}
