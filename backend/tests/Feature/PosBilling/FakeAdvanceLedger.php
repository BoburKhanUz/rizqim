<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\Order;
use App\Support\Money;
use RuntimeException;

/**
 * AdvanceLedger for the P3-18 tests: `deposit` per order id, and every offset applied.
 */
class FakeAdvanceLedger implements AdvanceLedger
{
    /** @var array<int, string> order id => deposit */
    public array $deposits = [];

    /** @var list<array{order_id: int, amount: string, bill_uuid: string}> */
    public array $applied = [];

    public function available(Order $order): Money
    {
        $used = Money::sum(...array_column(array_filter($this->applied, fn ($row) => $row['order_id'] === (int) $order->id), 'amount'));

        return Money::of($this->deposits[(int) $order->id] ?? 0)->minus($used);
    }

    public function apply(Order $order, Money $amount, string $billUuid): void
    {
        if ($amount->isGreaterThan($this->available($order))) {
            throw new RuntimeException('More than the deposit.');
        }

        $this->applied[] = ['order_id' => (int) $order->id, 'amount' => $amount->toString(), 'bill_uuid' => $billUuid];
    }
}
