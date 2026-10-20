<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case CUSTOMER = 'customer';
    case COURIER = 'courier';
    // Restaurant floor staff (POS/KDS); what each may do is App\Services\Auth\RolePermissions
    case CASHIER = 'cashier';
    case WAITER = 'waiter';
    case COOK = 'cook';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::OWNER => 'Owner',
            self::MANAGER => 'Manager',
            self::CUSTOMER => 'Customer',
            self::COURIER => 'Courier',
            self::CASHIER => 'Cashier',
            self::WAITER => 'Waiter',
            self::COOK => 'Cook',
        };
    }

    /**
     * Uzbek name shown to store staff (panel, API permission screens).
     */
    public function labelUz(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Superadmin',
            self::OWNER => 'Ega',
            self::MANAGER => 'Menejer',
            self::CUSTOMER => 'Mijoz',
            self::COURIER => 'Kuryer',
            self::CASHIER => 'Kassir',
            self::WAITER => 'Ofitsiant',
            self::COOK => 'Oshpaz',
        };
    }

    /**
     * Roles that only exist inside a store: such an account without a store_id is invalid.
     *
     * @return list<self>
     */
    public static function storeRoles(): array
    {
        return [self::OWNER, self::MANAGER, self::COURIER, self::CASHIER, self::WAITER, self::COOK];
    }

    public function isStoreStaff(): bool
    {
        return in_array($this, self::storeRoles(), true);
    }

    /**
     * Roles that sign in on a paired POS/KDS terminal with a PIN.
     *
     * @return list<self>
     */
    public static function terminalRoles(): array
    {
        return [self::OWNER, self::MANAGER, self::CASHIER, self::WAITER, self::COOK];
    }

    /**
     * Roles an owner may give to staff (never owner or super_admin).
     *
     * @return list<self>
     */
    public static function assignableStaffRoles(): array
    {
        return [self::MANAGER, self::CASHIER, self::WAITER, self::COOK, self::COURIER];
    }

    /**
     * Seniority inside a store: staff only manage (PINs, roles) people ranked below them.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SUPER_ADMIN => 100,
            self::OWNER => 50,
            self::MANAGER => 40,
            self::CASHIER, self::WAITER, self::COOK, self::COURIER => 10,
            self::CUSTOMER => 0,
        };
    }
}
