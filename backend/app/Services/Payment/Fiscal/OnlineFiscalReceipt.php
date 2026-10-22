<?php

namespace App\Services\Payment\Fiscal;

use App\Domain\Fiscal\FiscalLine;
use App\Support\Money;

/**
 * The receipt a payment provider (Payme, Click) registers for an online-paid order, with the restaurant as komitent
 * (P3-14). `problems` lists why it cannot be sent (missing MXIK, package code, store TIN...): the payment still goes
 * through, the problem is flagged on the payment for the owner (OnlineFiscalFlags).
 */
final readonly class OnlineFiscalReceipt
{
    /**
     * @param  list<FiscalLine>  $lines  dish and charge lines (service charge, place fee), amounts after discount
     * @param  list<string>  $problems  problem codes, empty when the receipt can be sent
     * @param  array<string, list<string>>  $details  names behind the problems (items without MXIK...), for the log
     */
    public function __construct(
        public bool $enabled,
        public ?string $commissionerTin,
        public array $lines,
        public Money $delivery,
        public ?FiscalLine $deliveryLine,
        public Money $total,
        public array $problems = [],
        public array $details = [],
    ) {}

    public static function disabled(Money $total): self
    {
        return new self(false, null, [], Money::zero(), null, $total);
    }

    /**
     * Fiscal receipts are on for the store and every line is complete.
     */
    public function isIssuable(): bool
    {
        return $this->enabled && $this->problems === [];
    }

    /**
     * Lines with the delivery as a receipt line (Click; Payme carries the delivery as `shipping`).
     *
     * @return list<FiscalLine>
     */
    public function linesWithDelivery(): array
    {
        return $this->deliveryLine ? [...$this->lines, $this->deliveryLine] : $this->lines;
    }

    /**
     * The same receipt with more problems (e.g. a provider-side precondition).
     *
     * @param  list<string>  $problems
     */
    public function withProblems(array $problems): self
    {
        return new self(
            $this->enabled,
            $this->commissionerTin,
            $this->lines,
            $this->delivery,
            $this->deliveryLine,
            $this->total,
            array_values(array_unique([...$this->problems, ...$problems])),
            $this->details,
        );
    }
}
