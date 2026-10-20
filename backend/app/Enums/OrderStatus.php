<?php

namespace App\Enums;

enum OrderStatus: string
{
    case NEW = 'new';
    case ACCEPTED = 'accepted';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case COURIER_ASSIGNED = 'courier_assigned';
    case ON_THE_WAY = 'on_the_way';
    case DELIVERY = 'delivery';
    case DELIVERED = 'delivered';
    case CANCELED = 'canceled';
    case REJECTED = 'rejected';
    case REFUNDED = 'refunded';
    
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Yangi',
            self::ACCEPTED => 'Qabul qilindi',
            self::PREPARING => 'Tayyorlanmoqda',
            self::READY => 'Tayyor',
            self::COURIER_ASSIGNED => 'Kuryer biriktirildi',
            self::ON_THE_WAY => 'Yo\'lda',
            self::DELIVERY => 'Yetkazishda',
            self::DELIVERED => 'Yetkazildi',
            self::CANCELED => 'Bekor qilindi',
            self::REJECTED => 'Rad etildi',
            self::REFUNDED => 'Qaytarildi',
        };
    }

    /**
     * Statuses reachable from this one. Delivery orders leave the kitchen with a courier (on_the_way and
     * delivery both mean "en route"); pickup/dine-in orders are handed over (preparing|ready -> delivered).
     * Who may make each move is decided by OrderPolicy::changeStatus.
     *
     * @return list<self>
     */
    public function allowedTransitions(OrderType|string|null $orderType): array
    {
        $type = $orderType instanceof OrderType ? $orderType : OrderType::tryFrom((string) $orderType);
        $byCourier = $type === OrderType::DELIVERY;

        return match ($this) {
            self::NEW => [self::ACCEPTED, self::REJECTED, self::CANCELED],
            self::ACCEPTED => [self::PREPARING, self::CANCELED],
            self::PREPARING => $byCourier
                ? [self::READY, self::COURIER_ASSIGNED, self::ON_THE_WAY, self::DELIVERY, self::CANCELED]
                : [self::READY, self::DELIVERED, self::CANCELED],
            self::READY => $byCourier
                ? [self::COURIER_ASSIGNED, self::ON_THE_WAY, self::DELIVERY, self::CANCELED]
                : [self::DELIVERED, self::CANCELED],
            self::COURIER_ASSIGNED => [self::ON_THE_WAY, self::DELIVERY, self::CANCELED],
            self::ON_THE_WAY, self::DELIVERY => [self::DELIVERED, self::CANCELED],
            self::DELIVERED => [self::REFUNDED],
            self::CANCELED, self::REJECTED, self::REFUNDED => [],
        };
    }

    public function canTransitionTo(self $to, OrderType|string|null $orderType): bool
    {
        return in_array($to, $this->allowedTransitions($orderType), true);
    }

    public function isCancellation(): bool
    {
        return $this === self::CANCELED || $this === self::REJECTED;
    }

    /**
     * Statuses in which the order is with (or waiting for) a courier.
     */
    public function isWithCourier(): bool
    {
        return in_array($this, self::withCourier(), true);
    }

    /**
     * @return list<self>
     */
    public static function withCourier(): array
    {
        return [self::COURIER_ASSIGNED, self::ON_THE_WAY, self::DELIVERY];
    }
}
