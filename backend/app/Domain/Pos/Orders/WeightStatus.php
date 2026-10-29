<?php

namespace App\Domain\Pos\Orders;

/**
 * P6-25 (B3): where a "weight later" line is (order_items.weight_status; null: an ordinary line sold by the quantity
 * typed). `awaiting`: the dish was chosen, its quantity is the estimate and no bill is made or printed while it waits;
 * `weighed`: the real weight was entered (who and when are on the line), the price follows it.
 */
enum WeightStatus: string
{
    case AWAITING = 'awaiting';
    case WEIGHED = 'weighed';

    public function label(): string
    {
        return match ($this) {
            self::AWAITING => "Og'irlik kutilmoqda",
            self::WEIGHED => 'Tortildi',
        };
    }
}
