<?php

namespace App\Domain\Fiscal;

use App\Support\Money;

/**
 * A receipt to register: built by the POS (bill close, refund, booking deposit, nasiya repayment) or by an online
 * payment, independent of the provider. `idempotencyKey` makes a retried issue return the same receipt. `soldAt` (P3-31)
 * is the moment of an offline sale whose receipt is asked for only once the till is back online.
 */
final readonly class FiscalDocument
{
    /**
     * @param  list<FiscalLine>  $lines
     * @param  list<FiscalPayment>  $payments
     */
    public function __construct(
        public FiscalReceiptType $type,
        public int $storeId,
        public ?int $branchId,
        public ?int $deviceId,
        public array $lines,
        public array $payments,
        public Money $total,
        public string $idempotencyKey,
        public ?string $originalReceiptUuid = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        // P3-31: when the sale happened, when earlier than the receipt (an offline sale receipted at sync)
        public ?\DateTimeInterface $soldAt = null,
    ) {}
}
