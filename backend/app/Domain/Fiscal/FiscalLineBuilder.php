<?php

namespace App\Domain\Fiscal;

use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;

/**
 * Turns POS lines into receipt lines with MXIK, unit and VAT taken from the menu and the store's tax regime (P3-11).
 * Charges that are not menu items (room fee, service charge) use the MXIK codes configured on the store.
 */
interface FiscalLineBuilder
{
    /**
     * The receipt line for (part of) an order line: `quantity` and `amount` may be a split-bill share.
     */
    public function forOrderItem(OrderItem $item, Quantity $quantity, Money $amount): FiscalLine;

    /**
     * The receipt line for a charge: `room_fee`, `place_fee`, `min_spend_topup`, `service_charge` or `deposit`.
     */
    public function forCharge(Store $store, string $charge, Money $amount): FiscalLine;
}
