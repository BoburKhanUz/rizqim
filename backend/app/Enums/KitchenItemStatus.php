<?php

namespace App\Enums;

/**
 * Where an order line is in the kitchen (order_items.kitchen_status, P2-11): `pending` until a cook bumps it on the
 * kitchen display, then `ready`. docs/POS_SPEC.md §10.3 reserves more values for the POS phase (sent, served, void);
 * they are added here when the POS starts sending lines to the kitchen.
 */
enum KitchenItemStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Tayyorlanmoqda',
            self::READY => 'Tayyor',
        };
    }
}
