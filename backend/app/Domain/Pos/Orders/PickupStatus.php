<?php

namespace App\Domain\Pos\Orders;

/**
 * P6-13 (A2): where a counter order with a queue number is for the customer waiting for it (orders.pickup_status,
 * PickupQueue): `preparing` (the kitchen has it, the number shows under "Tayyorlanmoqda"), `ready` (called: the
 * number shows under "Tayyor" and is announced), `handed` (the customer took it). Null: the order is not in the
 * pickup queue (a table order, a delivery, a POS counter order before its lines went to the kitchen, an online pickup
 * order of a branch without `queue_calling`). A POS counter order is tracked in every format (its kitchen lines outlive
 * a payment taken first); only where `queue_calling` is on is it shown on the board and announced.
 */
enum PickupStatus: string
{
    case PREPARING = 'preparing';
    case READY = 'ready';
    case HANDED = 'handed';

    public function label(): string
    {
        return match ($this) {
            self::PREPARING => 'Tayyorlanmoqda',
            self::READY => 'Tayyor',
            self::HANDED => 'Berildi',
        };
    }
}
