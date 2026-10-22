<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;

/**
 * FiscalLineBuilder for the P3-17 tests: a charge line with a recognisable MXIK per charge, 12% VAT included.
 */
class FakeFiscalLineBuilder implements FiscalLineBuilder
{
    public const MXIK = [
        'deposit' => '10202002004000001',
        'booking_fee' => '10202002003000002',
        'credit' => '10202002004000001',
    ];

    /** @var list<string> */
    public array $charges = [];

    public bool $missingMxik = false;

    public function forOrderItem(OrderItem $item, Quantity $quantity, Money $amount): FiscalLine
    {
        throw new \LogicException('not used by bookings');
    }

    public function forCharge(Store $store, string $charge, Money $amount): FiscalLine
    {
        $this->charges[] = $charge;
        if ($this->missingMxik) {
            throw \App\Domain\Fiscal\FiscalException::chargeWithoutMxik($charge, 'Avans');
        }

        return new FiscalLine(
            $charge,
            self::MXIK[$charge] ?? '10202002000000000',
            null,
            'xizmat',
            Quantity::of(1),
            $amount,
            $amount,
            '12',
            Money::of(\Brick\Math\BigDecimal::of($amount->toString())->multipliedBy(12)->dividedBy(112, 2, \Brick\Math\RoundingMode::HalfUp)),
        );
    }
}
