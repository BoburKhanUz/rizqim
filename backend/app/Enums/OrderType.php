<?php

namespace App\Enums;

enum OrderType: string
{
    case DELIVERY = 'delivery';
    case PICKUP = 'pickup';
    case DINE_IN = 'dine_in';
    
    public function label(): string
    {
        return match ($this) {
            self::DELIVERY => 'Delivery',
            self::PICKUP => 'Pickup',
            self::DINE_IN => 'Dine-In',
        };
    }
}
