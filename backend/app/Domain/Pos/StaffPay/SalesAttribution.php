<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23 (owner decision 30.10.2026): whose sales a paid bill is in a branch's pay statements when its table was handed
 * to another waiter (P6-24):
 *
 *  - `at_payment` (the default, the same as tips): the waiter of the order when the bill was paid
 *    (order_bills.waiter_id) — bills paid before the handover stay with the first waiter;
 *  - `current_waiter`: the order's waiter when the statement is computed (orders.waiter_id) — every bill of the order
 *    goes to the waiter who has the table last.
 *
 * Either way a bill of an order without a waiter is its maker's (order_bills.created_by), and a refund follows the
 * person of the bill it refunds.
 */
enum SalesAttribution: string
{
    case AT_PAYMENT = 'at_payment';
    case CURRENT_WAITER = 'current_waiter';

    public function label(): string
    {
        return match ($this) {
            self::AT_PAYMENT => "To'lov paytidagi ofitsiant",
            self::CURRENT_WAITER => 'Stolning oxirgi ofitsianti',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
