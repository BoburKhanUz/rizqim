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
 *
 * P2-33: the kitchen is keyed on the lines, not only the order's status. A delivery order a dispatcher handed to a
 * courier while it was still cooking (preparing -> courier_assigned) keeps its pending lines on the kitchen display:
 * they are bumped and recalled there with no status move at all (courier_assigned has no way back to preparing or on
 * to ready; the courier waits for the food). Once the order is on its way or finished its lines are closed
 * (OrderStatusService marks any still pending ready), so on_the_way and later stay 422.
 */
class KitchenService
{
    /**
     * Statuses in which the kitchen bumps and recalls lines (P2-33: courier_assigned too, without status moves). A
     * ready order also takes a recall (P2-30), which moves it back to preparing.
     */
    public const LINE_STATUSES = [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::COURIER_ASSIGNED];

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
     * had made it ready), with kitchen.manage. P2-33: also while a courier is assigned (no status move); once it is on
     * its way or delivered: 422.
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
            // Statuses the order moved to here, so the caller sends the same alerts as for a status button (P2-33: not the
            // quiet ones, see OrderStatusService)
            $statuses = [];

            // P3-12: a POS line is in the kitchen only once it was sent; a voided one is gone, a served one done. A
            // recalled POS line goes back to `sent` (still in the kitchen), an online one to `pending` as before
            if ($item->isVoid() || !$item->reachedKitchen()) {
                throw new OrderTransitionException(
                    $item->isVoid() ? "Bu taom o'chirilgan." : "Bu taom hali oshxonaga yuborilmagan.",
                    422,
                    'kitchen_status',
                );
            }
            if ($to === KitchenItemStatus::PENDING && $item->sent_at !== null) {
                $to = KitchenItemStatus::SENT;
            }
            if ($item->kitchen_status === KitchenItemStatus::SERVED && $to === KitchenItemStatus::READY) {
                return ['order' => $order, 'item' => $item, 'changed' => false, 'statuses' => $statuses];
            }

            if ($item->kitchen_status === $to) {
                return ['order' => $order, 'item' => $item, 'changed' => false, 'statuses' => $statuses];
            }

            // P2-30: a recall also while the order is ready (not yet with a courier or handed over)
            $recallFromReady = $to !== KitchenItemStatus::READY && $order->status === OrderStatus::READY;
            if (!$recallFromReady && !in_array($order->status, self::LINE_STATUSES, true)) {
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
                $result = $this->orderStatus->transition($order, OrderStatus::PREPARING, $actor, [
                    'notes' => "Oshxona tayyorlashni boshladi: {$actor->name}",
                ]);
                $order = $result->order;
                if (!$result->quiet) {
                    $statuses[] = OrderStatus::PREPARING;
                }
            }

            // P2-30: a line recalled from a ready order takes the order back to the kitchen (the next last bump makes it
            // ready again); OrderStatusService checks kitchen.manage and opens ready -> preparing for this call only
            if ($recallFromReady) {
                // P2-33: quiet (OrderStatusService): no alerts for the kitchen's own correction
                $order = $this->orderStatus->transition($order, OrderStatus::PREPARING, $actor, [
                    'kitchen_recall' => true,
                    'notes' => "Oshxona taomni qaytardi, buyurtma yana tayyorlanmoqda: {$actor->name}",
                ])->order;
            }

            $item->forceFill([
                'kitchen_status' => $to->value,
                'ready_at' => $to === KitchenItemStatus::READY ? now() : null,
            ])->save();

            // P2-33: with a courier already assigned the order stays courier_assigned (no status move either way)
            // P3-12: a POS line sent and not ready yet (or not sent yet) is still to cook too
            $pending = $order->items()->whereIn('kitchen_status', KitchenItemStatus::cookingValues())->exists();
            if ($to === KitchenItemStatus::READY && !$pending && $order->order_type !== OrderType::DINE_IN
                && $order->status !== OrderStatus::COURIER_ASSIGNED) {
                $result = $this->orderStatus->transition($order, OrderStatus::READY, $actor, [
                    'notes' => "Oshxonada barcha taomlar tayyor: {$actor->name}",
                ]);
                $order = $result->order;
                // P2-33: ready again after a recall is quiet: the customer and the couriers already heard it once
                if (!$result->quiet) {
                    $statuses[] = OrderStatus::READY;
                }
            }

            // Queued, after commit (ShouldDispatchAfterCommit), ids and kitchen state only
            event(new KitchenItemStatusChangedBroadcast($order->refresh(), $item));

            return ['order' => $order, 'item' => $item, 'changed' => true, 'statuses' => $statuses];
        });
    }
}
