<?php

namespace App\Services\Order;

use App\Domain\Pos\Orders\PickupQueue;
use App\Domain\Pos\Orders\PickupStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Audit\AuditRedactor;
use App\Services\Auth\PermissionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The order as the staff screens show it (P2-11, docs/API.md): the board card and detail, and the kitchen ticket.
 *
 * Who sees what: the customer's name and phone and the delivery address only reach staff who run orders
 * (orders.manage: owner, manager, cashier, waiter by default). A cook (orders.view + kitchen.manage) gets the food,
 * the notes with any phone number in them masked, and the times; never contact details. Kitchen tickets carry no
 * personal data and no prices for anyone.
 */
class StaffOrderPresenter
{
    /** Relations every staff shape reads; the customer and courier only for staff who run orders. */
    public const RELATIONS = ['items', 'branch:id,name', 'statusHistories'];

    public const CONTACT_RELATIONS = ['user:id,name,phone', 'courier:id,name,phone'];

    public function __construct(
        protected PermissionService $permissions,
    ) {}

    /**
     * Whether this viewer gets the customer's contact details and address (their visibility of the order itself is
     * the caller's check, so the branch is already right).
     */
    public function seesContact(User $viewer): bool
    {
        return $this->permissions->has($viewer, Permission::ORDERS_MANAGE);
    }

    /**
     * @return list<string>
     */
    public function relationsFor(User $viewer): array
    {
        return $this->seesContact($viewer) ? [...self::RELATIONS, ...self::CONTACT_RELATIONS] : self::RELATIONS;
    }

    /**
     * The board card (GET /staff/orders) and, with $detail, the detail screen (GET /staff/orders/{uuid}).
     *
     * @return array<string, mixed>
     */
    public function order(Order $order, User $viewer, bool $detail = false): array
    {
        $contact = $this->seesContact($viewer);
        $order->loadMissing($this->relationsFor($viewer));
        $times = $this->times($order);
        $items = $order->items->sortBy('id')->values();

        $data = [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'type' => $order->order_type->value,
            'channel' => $order->channel->value,
            'store_id' => (int) $order->store_id,
            'branch_id' => $order->branch_id !== null ? (int) $order->branch_id : null,
            'branch_name' => $order->branch?->name,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status->value,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'delivery_price' => $order->delivery_price,
            'service_charge_amount' => $order->service_charge_amount,
            'place_fee_amount' => $order->place_fee_amount,
            'total_price' => $order->total_price,
            'notes' => $contact ? $order->notes : $this->maskedNotes($order->notes),
            'guests_count' => $order->guests_count,
            'hall_ref' => $order->hall_ref,
            'table_ref' => $order->table_ref,
            'cancel_reason' => $order->cancel_reason?->value,
            'cancel_note' => $contact ? $order->cancel_note : $this->maskedNotes($order->cancel_note),
            'customer' => $contact && $order->user ? ['name' => $order->user->name, 'phone' => $order->user->phone] : null,
            'delivery' => $contact && $order->order_type->value === 'delivery' ? [
                'address' => $order->shipping_address['address'] ?? null,
                'latitude' => isset($order->shipping_address['latitude']) ? (float) $order->shipping_address['latitude'] : null,
                'longitude' => isset($order->shipping_address['longitude']) ? (float) $order->shipping_address['longitude'] : null,
                'zone_name' => $order->delivery_zone_name,
            ] : null,
            'courier' => $contact && $order->courier ? ['id' => (int) $order->courier->id, 'name' => $order->courier->name, 'phone' => $order->courier->phone] : null,
            // P3-12: a voided POS line is not counted; a served one is done
            'items_total' => $items->reject(fn (OrderItem $item) => $item->isVoid())->count(),
            'items_ready' => $this->doneCount($items),
            'items' => $this->prefetchDishes($items)->map(fn (OrderItem $item) => $this->item($item, true))->all(),
            'created_at' => $order->created_at?->toJSON(),
            'updated_at' => $order->updated_at?->toJSON(),
            'accepted_at' => $times['accepted_at']?->toJSON(),
            'preparing_at' => $times['preparing_at']?->toJSON(),
            'ready_at' => $times['ready_at']?->toJSON(),
            'closed_at' => $times['closed_at']?->toJSON(),
            // P2-30: the kitchen tickets' rule, so a board shows the same wait as the kitchen display
            'waiting_seconds' => $this->waitingSeconds($order, $times),
        ];

        if ($detail) {
            $data['status_history'] = $order->statusHistories->sortBy('id')->values()
                ->map(fn (OrderStatusHistory $history) => [
                    'from_status' => $history->from_status?->value,
                    'status' => $history->status->value,
                    'created_at' => $history->created_at?->toJSON(),
                ])->all();
        }

        return $data;
    }

    /**
     * A kitchen display ticket (GET /staff/kitchen/tickets): the food, notes and times, nothing personal, no prices.
     * waiting_seconds runs from acceptance (creation for an order never accepted) to now, or to when it became ready.
     *
     * @return array<string, mixed>
     */
    public function ticket(Order $order, ?Carbon $now = null): array
    {
        $order->loadMissing(self::RELATIONS);
        $times = $this->times($order);
        // P3-12: the kitchen sees a POS line once it was sent (a voided one struck through), and a held pre-order line
        // with its due time; a line still only on the order (not sent, or held for later) is not the kitchen's yet
        $items = $order->items->sortBy('id')->filter(fn (OrderItem $item) => self::kitchenSees($item))->values();

        return [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'type' => $order->order_type->value,
            'channel' => $order->channel->value,
            'branch_id' => $order->branch_id !== null ? (int) $order->branch_id : null,
            'hall_ref' => $order->hall_ref,
            'table_ref' => $order->table_ref,
            'guests_count' => $order->guests_count,
            'notes' => $this->maskedNotes($order->notes),
            'items_total' => $items->reject(fn (OrderItem $item) => $item->isVoid())->count(),
            'items_ready' => $this->doneCount($items),
            'items' => $this->prefetchDishes($items)->map(fn (OrderItem $item) => $this->item($item, false))->all(),
            'created_at' => $order->created_at?->toJSON(),
            'accepted_at' => $times['accepted_at']?->toJSON(),
            'preparing_at' => $times['preparing_at']?->toJSON(),
            'ready_at' => $times['ready_at']?->toJSON(),
            // P3-26: when a cancelled or rejected order left the kitchen (GET /staff/kitchen/tickets?canceled_within=)
            'canceled_at' => $order->status->isCancellation() ? $times['closed_at']?->toJSON() : null,
            'waiting_seconds' => $this->waitingSeconds($order, $times, $now),
            // P6-14: the counter number and where it is in the pickup queue (a paid-first order is `delivered` while
            // its number is still `preparing`): the kitchen display shows the number big and bumps it ready
            'queue_number' => $order->queue_number !== null ? (int) $order->queue_number : null,
            'pickup_status' => PickupQueue::statusOf($order),
        ];
    }

    /**
     * How long the order has waited: from acceptance (creation for an order never accepted) to now while it is new or
     * being cooked, else to when it became ready (or closed, if it never was): P2-11 kitchen tickets, P2-30 the board.
     *
     * @param  array{accepted_at: ?CarbonInterface, preparing_at: ?CarbonInterface, ready_at: ?CarbonInterface, closed_at: ?CarbonInterface}  $times
     */
    public function waitingSeconds(Order $order, array $times, ?Carbon $now = null): int
    {
        $start = $times['accepted_at'] ?? $order->created_at;
        $cooking = in_array($order->status, [OrderStatus::NEW, OrderStatus::ACCEPTED, OrderStatus::PREPARING], true)
            // P6-14: a counter order paid first is closed while its number is still being cooked
            || ($order->status === OrderStatus::DELIVERED && PickupQueue::statusOf($order) === PickupStatus::PREPARING);
        if (!$cooking && $order->status === OrderStatus::DELIVERED && PickupQueue::statusOf($order) === PickupStatus::READY && $order->pickup_ready_at !== null) {
            $times['ready_at'] ??= $order->pickup_ready_at;
        }
        // A finished order that never passed `ready` (a pickup handed over from the kitchen, a cancelled one) stops when
        // it closed, so a history row does not keep counting
        $end = $cooking ? ($now ?? now()) : ($times['ready_at'] ?? $times['closed_at'] ?? $now ?? now());

        return $start !== null ? max(0, (int) $start->diffInSeconds($end, false)) : 0;
    }

    /**
     * One line: quantity with its unit (1.5 kg), the chosen modifiers, and where it is in the kitchen.
     *
     * @return array<string, mixed>
     */
    public function item(OrderItem $item, bool $withPrices): array
    {
        $data = [
            'id' => (int) $item->id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'quantity' => $item->quantity,
            'unit' => $item->unit->value,
            'quantity_label' => $item->quantityLabel(),
            'modifiers' => array_map(fn (array $modifier) => array_filter([
                'name' => (string) ($modifier['name'] ?? ''),
                'group_name' => isset($modifier['group_name']) ? (string) $modifier['group_name'] : null,
                'quantity' => (int) ($modifier['quantity'] ?? 1),
                'price_delta' => $withPrices && isset($modifier['price_delta']) ? (string) $modifier['price_delta'] : null,
            ], fn ($value) => $value !== null), $item->modifiers ?? []),
            'kitchen_status' => $item->kitchen_status->value,
            'ready_at' => $item->ready_at?->toJSON(),
        ];

        // P6-15: the parts of a kompleks line: the kitchen prepares each (docs/POS_SPEC.md "Kompleks")
        if ($item->set_parts) {
            $data['set_parts'] = $item->set_parts;
        }

        // P3-12: a POS line also says its round, firing, station, seat, note and void ("O'CHIRILDI"); an online line
        // keeps exactly its P2-11 shape. Review P3-35: and its dish (product, category), so a display routes a line
        // without a station by the branch's station routes
        if ($item->fire_mode !== null) {
            [$productId, $categoryId] = $this->dishOf($item);
            $data += [
                'round_no' => $item->round_no,
                'fire_mode' => $item->fire_mode->value,
                'station_id' => $item->station_id,
                'product_id' => $productId,
                'category_id' => $categoryId,
                'sent_at' => $item->sent_at?->toJSON(),
                'due_at' => $item->due_at?->toJSON(),
                'seat_no' => $item->seat_no,
                'note' => $item->note,
                'voided_at' => $item->voided_at?->toJSON(),
                'void_reason' => $item->void_reason,
            ];
        }

        if ($withPrices) {
            $data += [
                'unit_price' => (string) $item->unit_price,
                'modifiers_total' => $item->modifiers_total,
                'total_price' => (string) $item->total_price,
            ];
        }

        return $data;
    }

    /** @var array<int, array{0: int|null, 1: int|null}> variant id => [product id, category id] */
    private array $dishes = [];

    /**
     * Reads the dishes (product, category) of the POS lines in one query, deleted variants and products included (the
     * line is a snapshot).
     *
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     * @return \Illuminate\Support\Collection<int, OrderItem>
     */
    private function prefetchDishes(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $missing = $items->filter(fn (OrderItem $item) => $item->fire_mode !== null && $item->product_variant_id !== null)
            ->map(fn (OrderItem $item) => (int) $item->product_variant_id)
            ->unique()
            ->reject(fn (int $id) => isset($this->dishes[$id]))
            ->values()
            ->all();
        if ($missing !== []) {
            foreach ($missing as $id) {
                $this->dishes[$id] = [null, null];
            }
            \App\Models\ProductVariant::withTrashed()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('product_variants.id', $missing)
                ->get(['product_variants.id', 'products.id as dish_product_id', 'products.category_id as dish_category_id'])
                ->each(function (\App\Models\ProductVariant $row) {
                    $categoryId = $row->getAttribute('dish_category_id');
                    $this->dishes[(int) $row->id] = [(int) $row->getAttribute('dish_product_id'), $categoryId !== null ? (int) $categoryId : null];
                });
        }

        return $items;
    }

    /**
     * @return array{0: int|null, 1: int|null} the line's product and category ids
     */
    private function dishOf(OrderItem $item): array
    {
        if ($item->product_variant_id === null) {
            return [null, null];
        }
        if (!isset($this->dishes[(int) $item->product_variant_id])) {
            $this->prefetchDishes(collect([$item]));
        }

        return $this->dishes[(int) $item->product_variant_id];
    }

    /**
     * When the order was accepted, started cooking, became ready and was closed (delivered, cancelled, rejected or
     * refunded), from its status history (the latest row of each).
     *
     * @return array{accepted_at: ?CarbonInterface, preparing_at: ?CarbonInterface, ready_at: ?CarbonInterface, closed_at: ?CarbonInterface}
     */
    public function times(Order $order): array
    {
        /** @var array<string, CarbonInterface|null> $at */
        $at = [];
        foreach ($order->statusHistories->sortBy('id') as $history) {
            $at[$history->status->value] = $history->created_at;
        }

        $closed = null;
        foreach (OrderQueryService::TERMINAL_STATUSES as $status) {
            $closed = $at[$status->value] ?? $closed;
        }

        return [
            'accepted_at' => $at[OrderStatus::ACCEPTED->value] ?? null,
            'preparing_at' => $at[OrderStatus::PREPARING->value] ?? null,
            'ready_at' => $at[OrderStatus::READY->value] ?? null,
            'closed_at' => in_array($order->status, OrderQueryService::TERMINAL_STATUSES, true) ? $closed : null,
        ];
    }

    /**
     * P3-12: whether the kitchen display shows a line: an online line always; a POS line once sent (also when voided
     * after that), or held with a due time (a pre-order the kitchen plans by time).
     */
    public static function kitchenSees(OrderItem $item): bool
    {
        if ($item->reachedKitchen()) {
            return true;
        }

        return $item->fire_mode === \App\Enums\FireMode::HOLD && $item->due_at !== null && !$item->isVoid();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     */
    private function doneCount(\Illuminate\Support\Collection $items): int
    {
        return $items->filter(fn (OrderItem $item) => in_array($item->kitchen_status->value, \App\Enums\KitchenItemStatus::doneValues(), true))->count();
    }

    /**
     * Free text for staff who do not run orders: a phone number typed into a note is masked.
     */
    protected function maskedNotes(?string $notes): ?string
    {
        return $notes === null ? null : (string) (AuditRedactor::clean(['notes' => $notes])['notes'] ?? '');
    }
}
