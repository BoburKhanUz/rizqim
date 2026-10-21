<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;

/**
 * FiscalLineBuilder for the P3-18 tests: a line named after the order line or the charge, no VAT; an order line whose
 * name has "MXIKSIZ" has no MXIK (FiscalException, as the real builder).
 */
class FakeFiscalLineBuilder implements FiscalLineBuilder
{
    public function forOrderItem(OrderItem $item, Quantity $quantity, Money $amount): FiscalLine
    {
        if (str_contains((string) $item->product_name, 'MXIKSIZ')) {
            throw FiscalException::missingMxik((string) $item->product_name);
        }

        return new FiscalLine((string) $item->product_name, '10202001001000001', null, 'dona', $quantity, Money::of($item->unit_price), $amount, '0', Money::zero());
    }

    public function forCharge(Store $store, string $charge, Money $amount): FiscalLine
    {
        return new FiscalLine($charge, '10202002001000001', null, 'xizmat', Quantity::of(1), $amount, $amount, '0', Money::zero());
    }
}
