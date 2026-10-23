<?php

namespace App\Domain\Pos\Sync;

use App\Enums\Permission;

/**
 * What a till may do offline (docs/POS_SPEC.md §5.1), each applied through the POS domain services on sync:
 * open a walk-in or room order, add a round, send lines to the kitchen, void an unsent line, mark a line served, make
 * one bill, take cash or card for it, close the order.
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
            default => [Permission::ORDERS_MANAGE],
        };
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
        };
    }
}
