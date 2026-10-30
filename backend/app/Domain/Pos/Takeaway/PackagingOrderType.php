<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Pos\Billing\ServiceOrderType;
use App\Enums\OrderType;
use App\Models\Order;

/**
 * P6-34 (B8): the orders a packaging rule applies to. `takeaway`: a POS counter order taken away (pos_takeaway without a
 * table plate); `pickup` / `delivery`: a Telegram, Mini App or mobile order picked up or delivered. The hall (a hall
 * order, a counter order carried to the table) never gets packaging.
 */
enum PackagingOrderType: string
{
    case TAKEAWAY = 'takeaway';
    case PICKUP = 'pickup';
    case DELIVERY = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::TAKEAWAY => 'Kassada olib ketish',
            self::PICKUP => 'Telegram: olib ketish',
            self::DELIVERY => 'Yetkazib berish',
        };
    }

    /**
     * The packaging type of an order; null: no packaging (the hall).
     */
    public static function ofOrder(Order $order): ?self
    {
        if ($order->channel->isPos()) {
            return ServiceOrderType::of($order) === ServiceOrderType::TAKEAWAY ? self::TAKEAWAY : null;
        }

        return self::ofOnline($order->order_type);
    }

    /**
     * An online order's type (CreateOrderAction's `type`): pickup or delivery; a dine-in order none.
     */
    public static function ofOnline(OrderType|string|null $type): ?self
    {
        $value = $type instanceof OrderType ? $type->value : $type;

        return match ($value) {
            OrderType::PICKUP->value => self::PICKUP,
            OrderType::DELIVERY->value => self::DELIVERY,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
