<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case CUSTOMER = 'customer';
    case COURIER = 'courier';
    
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::OWNER => 'Owner',
            self::MANAGER => 'Manager',
            self::CUSTOMER => 'Customer',
            self::COURIER => 'Courier',
        };
    }
}
