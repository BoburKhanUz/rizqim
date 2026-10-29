<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23: what a guarantee's percent is taken of: the person's own sales, or the service charge they collected.
 */
enum PercentBase: string
{
    case SALES = 'sales';
    case SERVICE_CHARGE = 'service_charge';

    public function label(): string
    {
        return match ($this) {
            self::SALES => 'Sotuvdan',
            self::SERVICE_CHARGE => 'Xizmat haqidan',
        };
    }
}
