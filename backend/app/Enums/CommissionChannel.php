<?php

namespace App\Enums;

/**
 * P4-21: which commission rate an order pays (orders.commission_channel; docs/BILLING.md, "Komissiya"): the default
 * rates are config('billing.commission'), a store may have its own per channel (stores.commission_overrides).
 */
enum CommissionChannel: string
{
    case RIZQIM_COURIER = 'rizqim_courier';
    case OWN_DELIVERY = 'own_delivery';
    case PICKUP = 'pickup';
    case POS = 'pos';
    case AGGREGATOR = 'aggregator';

    public function label(): string
    {
        return match ($this) {
            self::RIZQIM_COURIER => 'RIZQIM kuryeri',
            self::OWN_DELIVERY => "O'z yetkazishi",
            self::PICKUP => 'Olib ketish',
            self::POS => 'Zal (POS)',
            self::AGGREGATOR => 'Agregator',
        };
    }

    /**
     * Channels of the Mini App and the mobile app, where the store's legacy flat rate (stores.commission_rate) and a
     * legacy plan's commission_percent still apply.
     */
    public function isOnline(): bool
    {
        return in_array($this, [self::RIZQIM_COURIER, self::OWN_DELIVERY, self::PICKUP], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $channel) => $channel->value, self::cases());
    }
}
