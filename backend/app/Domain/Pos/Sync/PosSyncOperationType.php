<?php

namespace App\Domain\Pos\Sync;

use App\Enums\Permission;

/**
 * What a till may do offline (docs/POS_SPEC.md §5.1), each applied through the POS domain services on sync:
 * open a walk-in or room order, add a round, send lines to the kitchen, void an unsent line, mark a line served, make
 * one bill, take cash or card for it, close the order; P6-21 / P6-25: send a held course, enter a line's real weight.
 * P6 local bridge (docs/POS_SPEC.md §5.5): the kitchen's bump, recall and expo assembly and the waiter's bill request,
 * which the hub relays from the KDS and the waiters' phones (relayable()).
 */
enum PosSyncOperationType: string
{
    case ORDER_OPEN = 'order.open';
    case ORDER_ROUND = 'order.round';
    case ORDER_SEND = 'order.send';
    case ORDER_VOID = 'order.void';
    case ORDER_SERVED = 'order.served';
    case BILL_CREATE = 'bill.create';
    case BILL_PAY = 'bill.pay';
    case ORDER_CLOSE = 'order.close';
    // P6-21 / P6-25: send a held course, the real weight of a "weight later" line
    case ORDER_COURSE_SEND = 'order.course_send';
    case ORDER_WEIGHT = 'order.weight';
    // P6 local bridge: the KDS and the waiter app through the branch's hub (the till itself may send them too)
    case KITCHEN_BUMP = 'kitchen.bump';
    case KITCHEN_RECALL = 'kitchen.recall';
    case KITCHEN_ASSEMBLED = 'kitchen.assembled';
    case BILL_REQUEST = 'bill.request';

    /**
     * The permissions the staff member must hold (any of them).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::BILL_CREATE => [Permission::ORDERS_MANAGE, Permission::POS_TAKE_PAYMENT],
            self::BILL_PAY, self::ORDER_CLOSE => [Permission::POS_TAKE_PAYMENT],
            self::KITCHEN_BUMP, self::KITCHEN_RECALL, self::KITCHEN_ASSEMBLED => [Permission::KITCHEN_MANAGE],
            default => [Permission::ORDERS_MANAGE],
        };
    }

    /**
     * What the branch's hub may relay for another device (docs/POS_SPEC.md §5.5): never money, a bill or closing an
     * order (those stay on the till itself).
     */
    public function relayable(): bool
    {
        return !in_array($this, [self::BILL_CREATE, self::BILL_PAY, self::ORDER_CLOSE], true);
    }

    /**
     * Money taken: never rejected (POS_SPEC 5.3), recorded even when the order cannot take it any more.
     */
    public function isMoney(): bool
    {
        return $this === self::BILL_PAY;
    }

    public function label(): string
    {
        return match ($this) {
            self::ORDER_OPEN => 'Buyurtma ochildi',
            self::ORDER_ROUND => "Bosqich qo'shildi",
            self::ORDER_SEND => 'Oshxonaga yuborildi',
            self::ORDER_VOID => "Taom o'chirildi",
            self::ORDER_SERVED => 'Taom berildi',
            self::BILL_CREATE => 'Schyot tuzildi',
            self::BILL_PAY => "To'lov olindi",
            self::ORDER_CLOSE => 'Buyurtma yopildi',
            self::ORDER_COURSE_SEND => 'Kurs oshxonaga yuborildi',
            self::ORDER_WEIGHT => "Og'irlik kiritildi",
            self::KITCHEN_BUMP => 'Taom tayyor',
            self::KITCHEN_RECALL => 'Taom oshxonaga qaytarildi',
            self::KITCHEN_ASSEMBLED => "Buyurtma yig'ildi",
            self::BILL_REQUEST => "Schyot so'raldi",
        };
    }
}
