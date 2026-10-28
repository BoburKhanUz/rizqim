<?php

namespace App\Services\Finance\Netting;

use App\Support\Money;

/**
 * P4-23: what a netting took from an amount for the store's debt ("Ushlangan qarz") and what is left of it
 * ("O'tkaziladi" for a payout, the wallet's part for a settlement).
 */
final class NettingResult
{
    /**
     * @param  list<NettingLine>  $lines
     */
    public function __construct(
        public readonly Money $amount,
        public readonly Money $netted,
        public readonly array $lines,
    ) {}

    public static function none(Money $amount): self
    {
        return new self($amount, Money::zero(), []);
    }

    public function remainder(): Money
    {
        return $this->amount->minus($this->netted);
    }

    /**
     * @return list<array{kind: string, label: string, amount: string, invoice: array{id: int, uuid: string, number: string, period: string}|null, transaction_id: int|null}>
     */
    public function linesArray(): array
    {
        return array_map(fn (NettingLine $line) => $line->toArray(), $this->lines);
    }
}
