<?php

namespace App\Domain\Fiscal;

/**
 * Receipt types of VM 943 (1-ilova, 7-band). `advance` (bo'nak) and `credit` (kredit) receipts carry no fiscal sign
 * or QR: an advance is taken against a booking and offset in the later `sale` receipt; a credit receipt records each
 * repayment of a sale made on credit (nasiya), whose full `sale` receipt was issued when the food was served.
 */
enum FiscalReceiptType: string
{
    case SALE = 'sale';
    case REFUND = 'refund';
    case ADVANCE = 'advance';
    case CREDIT = 'credit';
}
