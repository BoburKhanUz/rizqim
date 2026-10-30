<?php

namespace App\Domain\Pos\Billing;

use App\Enums\OrderChannel;
use App\Models\Order;

/**
 * P6-22 (C1): the order type the service charge rules speak of (ServiceChargeRules::$byOrderType): `hall` — a waiter
 * serves the table (pos_hall); `table_service` — a counter order carried to the guest's table plate (P6-25,
 * orders.table_label); `takeaway` — any other counter order.
 */
enum ServiceOrderType: string
{
    case HALL = 'hall';
    case TABLE_SERVICE = 'table_service';
    case TAKEAWAY = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::HALL => 'Zalda (ofitsiant)',
            self::TABLE_SERVICE => 'Stolga olib kelish',
            self::TAKEAWAY => 'Olib ketish / kassa',
        };
    }

    /**
     * Guests eat in: the large-group rule and the cover apply.
     */
    public function isDineIn(): bool
    {
        return $this !== self::TAKEAWAY;
    }

    public static function of(Order $order): self
    {
        if ($order->channel === OrderChannel::POS_HALL) {
            return self::HALL;
        }
        $label = $order->getAttribute('table_label');

        return is_string($label) && trim($label) !== '' ? self::TABLE_SERVICE : self::TAKEAWAY;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
