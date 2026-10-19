<?php

namespace App\Enums;

/**
 * Why an order was cancelled or rejected (orders.cancel_reason). Every cancellation made from now on carries one;
 * rows cancelled before the column existed have none. Only `no_show` counts towards the cash pickup ban
 * (Order::cashPickupBanApplies).
 */
enum CancelReason: string
{
    case CUSTOMER_REQUEST = 'customer_request';
    case NO_SHOW = 'no_show';
    case OUT_OF_STOCK = 'out_of_stock';
    case STORE_CLOSED = 'store_closed';
    case COURIER_UNAVAILABLE = 'courier_unavailable';
    case PAYMENT_TIMEOUT = 'payment_timeout';
    case OTHER = 'other';

    /**
     * Staff-facing label (Telegram buttons and cards, admin panel).
     */
    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => "Mijoz so'rovi",
            self::NO_SHOW => 'Mijoz kelmadi',
            self::OUT_OF_STOCK => 'Mahsulot qolmagan',
            self::STORE_CLOSED => "Do'kon yopiq",
            self::COURIER_UNAVAILABLE => "Kuryer yo'q",
            self::PAYMENT_TIMEOUT => "To'lov muddati o'tdi",
            self::OTHER => 'Boshqa sabab',
        };
    }

    /**
     * The reason as the customer reads it in their cancellation message.
     */
    public function customerLabel(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => "Sizning so'rovingizga ko'ra",
            self::NO_SHOW => 'Buyurtma olib ketilmadi',
            self::OUT_OF_STOCK => 'Mahsulot qolmagan',
            self::STORE_CLOSED => "Do'kon hozir ishlamayapti",
            self::COURIER_UNAVAILABLE => "Bo'sh kuryer topilmadi",
            self::PAYMENT_TIMEOUT => "To'lov belgilangan vaqtda amalga oshirilmadi",
            self::OTHER => 'Boshqa sabab',
        };
    }

    /**
     * Reasons staff (owner, manager, super admin) may give; payment_timeout is the system's own.
     *
     * @return list<self>
     */
    public static function staffCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason) => $reason !== self::PAYMENT_TIMEOUT));
    }

    /**
     * Whether the reason may be given for cancelling an order of this type that is in $from. "No show" only for a pickup
     * order the store already accepted (accepted, preparing, ready): the cash pickup ban counts it, so staff must not be
     * able to give it for an order the customer could not have collected yet.
     */
    public function appliesTo(OrderType|string|null $orderType, OrderStatus $from): bool
    {
        if ($this !== self::NO_SHOW) {
            return true;
        }

        $type = $orderType instanceof OrderType ? $orderType : OrderType::tryFrom((string) $orderType);

        return $type === OrderType::PICKUP
            && in_array($from, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true);
    }

    /**
     * @return array<string, string> value => staff label of the reasons staff may give for cancelling this order now
     */
    public static function staffOptionsFor(OrderType|string|null $orderType, OrderStatus $from): array
    {
        return array_filter(self::staffOptions(), fn (string $value) => self::from($value)->appliesTo($orderType, $from), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(fn (self $reason) => $reason->value, self::staffCases());
    }

    /**
     * @return array<string, string> value => staff label, for select inputs
     */
    public static function staffOptions(): array
    {
        $options = [];
        foreach (self::staffCases() as $reason) {
            $options[$reason->value] = $reason->label();
        }

        return $options;
    }
}
