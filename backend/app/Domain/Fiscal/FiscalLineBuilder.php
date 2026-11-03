<?php

namespace App\Domain\Fiscal;

use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;

/**
 * Turns POS lines into receipt lines with MXIK, unit and VAT taken from the menu and the store's tax regime (P3-11).
 * Charges that are not menu items (room fee, service charge) use the MXIK codes configured on the store. In fiscal
 * mode a line without an MXIK code is refused (FiscalException::MXIK_MISSING); a store whose fiscal mode is off gets
 * the line with an empty code (its receipts are non_fiscal).
 */
interface FiscalLineBuilder
{
    /**
     * The receipt line for (part of) an order line: `quantity` and `amount` may be a split-bill share.
     */
    public function forOrderItem(OrderItem $item, Quantity $quantity, Money $amount): FiscalLine;

    /**
     * The receipt line for a charge (FiscalSettings::CHARGES): `room_fee` (xona puli), `place_fee` (joy puli),
     * `min_spend_topup` (minimal summaga qo'shimcha), `service_charge` (xizmat haqi), `deposit` (booking advance, the
     * ADVANCE receipt's line), `booking_fee` (a retained deposit sold as the booking service), `credit` (a nasiya
     * repayment, the CREDIT receipt's line), `delivery` (an online order's delivery) or `packaging` (P6-34: the
     * default packaging line; App\Domain\Pos\Takeaway\PackagingFiscal gives each packaging line its own code). Any
     * other: UNKNOWN_CHARGE.
     */
    public function forCharge(Store $store, string $charge, Money $amount): FiscalLine;
}
