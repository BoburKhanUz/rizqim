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
}
