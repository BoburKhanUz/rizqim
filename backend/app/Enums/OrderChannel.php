<?php

namespace App\Enums;

/**
 * Where an order came from (orders.channel). One Order model serves every channel (docs/ROADMAP.md): the kitchen sees
 * them all, the stop-list reaches them all, and channel prices/visibility (channel_prices) differ per channel.
 */
enum OrderChannel: string
{
    case TELEGRAM = 'telegram';
    case MOBILE = 'mobile';
    case POS_HALL = 'pos_hall';
    case POS_TAKEAWAY = 'pos_takeaway';
    case AGGREGATOR = 'aggregator';

    public function label(): string
    {
        return match ($this) {
            self::TELEGRAM => 'Telegram',
            self::MOBILE => 'Mobil ilova',
            self::POS_HALL => 'Zal (POS)',
            self::POS_TAKEAWAY => 'Olib ketish (POS)',
            self::AGGREGATOR => 'Agregator',
        };
    }

    /**
     * Guests eat in the hall: the only channel the service charge and place fee apply to by default.
     */
    public function isDineIn(): bool
    {
        return $this === self::POS_HALL;
    }

    /**
     * Channels a customer may name when placing an order through POST /orders (POS and aggregators get their own
     * endpoints later).
     *
     * @return list<string>
     */
    public static function customerValues(): array
    {
        return [self::TELEGRAM->value, self::MOBILE->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $channel) => $channel->value, self::cases());
    }
}
