<?php

namespace App\Domain\Fiscal;

use App\Support\Money;

/**
 * Money on one receipt by one method. Two flags mark a part of a SALE that is not money received now (at most one of
 * them per payment, FiscalDocumentValidator):
 *  - `prepayment`: already paid by an earlier advance receipt (the booking deposit offset, bo'nak → sotuv);
 *  - `credit`: sold on credit (nasiya): the sale is receipted in full when the food is served and each repayment later
 *    gets its own CREDIT receipt (sotuv → kredit). Its `form` is only a placeholder (cash) until the provider says how
 *    an unpaid part is shown (POS_SPEC 4.9, T4).
 */
final readonly class FiscalPayment
{
    public function __construct(
        public FiscalPaymentForm $form,
        public Money $amount,
        public bool $prepayment = false,
        public bool $credit = false,
        public ?string $cardType = null,
        public ?string $transactionId = null,
        // P6-16: the card's last four digits as the bank terminal's slip shows them (optional; never the full number)
        public ?string $cardLast4 = null,
    ) {}
}
