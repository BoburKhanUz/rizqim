<?php

namespace App\Domain\Pos\Contracts;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * P6-13: what the order core (OrderStatusService, KitchenService) tells the POS pickup queue, and asks it. Bound by
 * PosQueueServiceProvider to App\Domain\Pos\Orders\PickupQueue; unbound, the core behaves as before.
 */
interface PickupTracker
{
    /**
     * The order's status moved (inside its transaction, the row locked): an online pickup order gets its number when
     * accepted and follows its status (ready, handed); a cancelled order leaves the queue.
     */
    public function statusChanged(Order $order, OrderStatus $from, OrderStatus $to): void;

    /**
     * A line of the order was bumped or recalled in the kitchen (inside the transaction): all ready → `ready`, a
     * line back in the kitchen → `preparing`.
     */
    public function kitchenChanged(Order $order): void;

    /**
     * Whether the kitchen still cooks this order although it is closed: a counter order paid first (delivered) whose
     * number is still `preparing`. Its sent lines are not closed by the payment and the kitchen display keeps it.
     */
    public function keepsKitchen(Order $order): bool;
}
