<?php

namespace App\Domain\Pos\Accounts;

/**
 * customer_accounts.kind (P6-44): `customer` is nasiya as in P3-17 (a person or a group; a charge at the till always
 * needs customer_accounts.manage or a manager's approval); `corporate` is a company whose staff eat on its account
 * (members with talons, daily limits or meals per day, a monthly limit and statement; a charge needs approval only when
 * the owner switched `charge_approval` on).
 */
enum CustomerAccountKind: string
{
    case CUSTOMER = 'customer';
    case CORPORATE = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Nasiya',
            self::CORPORATE => 'Korxona',
        };
    }
}
