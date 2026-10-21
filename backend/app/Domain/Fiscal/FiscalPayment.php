<?php

namespace App\Domain\Fiscal;

use App\Support\Money;

/**
 * Money received on one receipt by one method. `prepayment` marks the part of a sale already paid by an earlier
 * advance receipt (the booking deposit offset), so it is not money received now.
 */
final readonly class FiscalPayment
{
    public function __construct(
        public FiscalPaymentForm $form,
        public Money $amount,
        public bool $prepayment = false,
        public ?string $cardType = null,
        public ?string $transactionId = null,
    ) {}
}
