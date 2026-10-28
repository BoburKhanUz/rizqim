<?php

namespace App\Services\Admin\Metrics;

use Brick\Math\BigDecimal;

/**
 * P4-31: the recurring revenue at one moment: what every paying store's subscription is worth per month (a store
 * with nothing to pay, or still in its trial, is not in it), and how many stores are in a trial.
 */
final class MrrSnapshot
{
    /**
     * @param  array<int, BigDecimal>  $byStore  store id => monthly recurring revenue (positive amounts only)
     */
    public function __construct(
        public readonly array $byStore,
        public readonly int $trialing,
    ) {}

    public function total(): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($this->byStore as $amount) {
            $sum = $sum->plus($amount);
        }

        return $sum->toScale(2);
    }

    public function payingStores(): int
    {
        return count($this->byStore);
    }
}
