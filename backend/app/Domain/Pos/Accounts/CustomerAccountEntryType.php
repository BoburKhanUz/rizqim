<?php

namespace App\Domain\Pos\Accounts;

/**
 * customer_account_entries.type: charge (a bill closed on credit), repayment (money received, CREDIT receipt),
 * adjustment (a manager's correction, e.g. a debt written off).
 */
enum CustomerAccountEntryType: string
{
    case CHARGE = 'charge';
    case REPAYMENT = 'repayment';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::CHARGE => 'Nasiyaga yopilgan hisob',
            self::REPAYMENT => "Qarz to'lovi",
            self::ADJUSTMENT => 'Tuzatish',
        };
    }
}
