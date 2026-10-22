<?php

namespace App\Domain\Fiscal;

use App\Support\Money;
use App\Support\Quantity;

/**
 * One receipt line. Every line carries its MXIK code, unit and VAT rate: a dish, a service (room fee, service charge,
 * cooking service, kazan rent) or a deposit alike. `amount` is the line total after its share of discounts.
 */
final readonly class FiscalLine
{
    public function __construct(
        public string $name,
        public string $mxikCode,
        public ?string $packageCode,
        public string $unit,
        public Quantity $quantity,
        public Money $unitPrice,
        public Money $amount,
        public string $vatRate,
        public Money $vatAmount,
        public ?string $commissionerTin = null,
        public ?string $markingCode = null,
    ) {}
}
