<?php

namespace App\Services\Order;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\Broadcast\KitchenItemStatusChangedBroadcast;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The kitchen display's per line "ready" (bump) and its undo (recall), P2-11, docs/API.md.
 *
 * Under the order's row lock, like every status change. A line can be bumped while the order is accepted or
 * preparing, and recalled also while it is ready (P2-30: the order goes back to preparing); bumping a line of an accepted order starts it (accepted -> preparing). When the last line is ready a
 * delivery or pickup order becomes `ready` by itself (the customer or courier is waiting for it); a hall order
 * (dine_in) stays `preparing` until it is closed (docs/POS_SPEC.md §3), its lines show ready one by one. Every status
 * change goes through OrderStatusService (permission, history, audit, events); each line change broadcasts
 * kitchen.item_status_changed.
 */
class KitchenService
{
    public function __construct(
        protected OrderStatusService $orderStatus,
    ) {}

    /**
     * @return array{order: Order, item: OrderItem, changed: bool, statuses: list<OrderStatus>}
     *
     * @throws OrderTransitionException
     */
    public function bump(Order $order, OrderItem $item, User $actor): array
    {
        return $this->change($order, $item, $actor, KitchenItemStatus::READY);
    }

    /**
     * Undo a mistaken bump: the line is back to pending. While the order is still being cooked, and (P2-30) while it
     * is `ready` but not yet with a courier or handed over: then the order goes back to `preparing` too (the last bump
     * had made it ready), with kitchen.manage. Once a courier is assigned, it is on its way or delivered: 422.
     *
     * @return array{order: Order, item: OrderItem, changed: bool, statuses: list<OrderStatus>}
     *
     * @throws OrderTransitionException
     */
    public function recall(Order $order, OrderItem $item, User $actor): array
    {
        return $this->change($order, $item, $actor, KitchenItemStatus::PENDING);
    }

    /**
     * @return array{order: Order, item: OrderItem, changed: bool, statuses: list<OrderStatus>}
     */
    protected function change(Order $order, OrderItem $item, User $actor, KitchenItemStatus $to): array
    {
        return DB::transaction(function () use ($order, $item, $actor, $to) {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $item = OrderItem::whereKey($item->getKey())->where('order_id', $order->id)->firstOrFail();
            // Statuses the order moved to here, so the caller sends the same alerts as for a status button
            $statuses = [];

            if ($item->kitchen_status === $to) {
                return ['order' => $order, 'item' => $item, 'changed' => false, 'statuses' => $statuses];
            }

            // P2-30: a recall also while the order is ready (not yet with a courier or handed over)
            $recallFromReady = $to === KitchenItemStatus::PENDING && $order->status === OrderStatus::READY;
            if (!$recallFromReady && !in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING], true)) {
                throw new OrderTransitionException(
                    $to === KitchenItemStatus::READY
                        ? "Bu buyurtma oshxonada emas: uning taomlarini \"tayyor\" deb belgilab bo'lmaydi."
                        : "Bu buyurtmaning taomini qaytarib bo'lmaydi: u allaqachon oshxonadan chiqqan.",
                    422,
                    'kitchen_status',
                );
            }

            // The first bump starts the order (the permission is checked by OrderStatusService)
            if ($to === KitchenItemStatus::READY && $order->status === OrderStatus::ACCEPTED) {
                $order = $this->orderStatus->transition($order, OrderStatus::PREPARING, $actor, [
                    'notes' => "Oshxona tayyorlashni boshladi: {$actor->name}",
                ])->order;
                $statuses[] = OrderStatus::PREPARING;
            }

            // P2-30: a line recalled from a ready order takes the order back to the kitchen (the next last bump makes it
            // ready again); OrderStatusService checks kitchen.manage and opens ready -> preparing for this call only
            if ($recallFromReady) {
                $order = $this->orderStatus->transition($order, OrderStatus::PREPARING, $actor, [
                    'kitchen_recall' => true,
                    'notes' => "Oshxona taomni qaytardi, buyurtma yana tayyorlanmoqda: {$actor->name}",
                ])->order;
                $statuses[] = OrderStatus::PREPARING;
            }

            $item->forceFill([
                'kitchen_status' => $to->value,
                'ready_at' => $to === KitchenItemStatus::READY ? now() : null,
            ])->save();

            $pending = $order->items()->where('kitchen_status', KitchenItemStatus::PENDING->value)->exists();
            if ($to === KitchenItemStatus::READY && !$pending && $order->order_type !== OrderType::DINE_IN) {
                $order = $this->orderStatus->transition($order, OrderStatus::READY, $actor, [
                    'notes' => "Oshxonada barcha taomlar tayyor: {$actor->name}",
                ])->order;
                $statuses[] = OrderStatus::READY;
            }

            // Queued, after commit (ShouldDispatchAfterCommit), ids and kitchen state only
            event(new KitchenItemStatusChangedBroadcast($order->refresh(), $item));

            return ['order' => $order, 'item' => $item, 'changed' => true, 'statuses' => $statuses];
        });
    }
}
