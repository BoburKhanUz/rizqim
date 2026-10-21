<?php

namespace App\Domain\Pos\Shifts;

/**
 * cash_movements.type: what moved money into or out of the drawer (POS_SPEC 2.3, research R21, L7, L10). The cashier
 * records `cash_in`, `cash_out`, `wage` (to an employee) and `purchase` (bazaar shopping) by hand; the others are
 * written by the shift, sale and refund flows themselves. Booking deposits and nasiya repayments taken in cash (P3-17),
 * and cash deposit refunds, are read from their own tables by the shift report (ShiftReport), not copied here.
 */
enum CashMovementType: string
{
    case OPENING = 'opening';
    case SALE = 'sale';
    case REFUND = 'refund';
    case CASH_IN = 'cash_in';
    case CASH_OUT = 'cash_out';
    case WAGE = 'wage';
    case PURCHASE = 'purchase';

    /** Types the cashier records by hand (POST /pos/shifts/{uuid}/cash-movements). */
    public const MANUAL = ['cash_in', 'cash_out', 'wage', 'purchase'];

    /**
     * Categories per manual type; `other` needs a note.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return match ($this) {
            self::CASH_IN => ['change_fund', 'owner', 'other'],
            self::CASH_OUT => ['collection', 'owner', 'supplies', 'utilities', 'other'],
            self::WAGE => ['daily_wage', 'advance', 'bonus'],
            self::PURCHASE => ['bazaar', 'meat', 'bread', 'supplies', 'other'],
            default => [],
        };
    }

    /**
     * +1 into the drawer, -1 out of it.
     */
    public function sign(): int
    {
        return match ($this) {
            self::OPENING, self::SALE, self::CASH_IN => 1,
            self::REFUND, self::CASH_OUT, self::WAGE, self::PURCHASE => -1,
        };
    }
}
