<?php

namespace App\Domain\Fiscal\Gateways;

use App\Support\Money;

/**
 * A smart kassa's card money for a period as its provider reports it (SmartKassaGateway::cardTotals): the net total
 * (sales less refunds), how many card sales, and the sales with their transaction (RRN) numbers.
 */
final readonly class TerminalCardTotals
{
    /**
     * @param  list<array{rrn: string|null, amount: string}>  $transactions
     */
    public function __construct(
        public Money $total,
        public int $count,
        public array $transactions = [],
    ) {}
}
