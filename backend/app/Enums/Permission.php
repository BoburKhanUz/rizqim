<?php

namespace App\Enums;

/**
 * What a store staff member may do. Each role gets a default set (App\Services\Auth\RolePermissions) that an owner can
 * adjust per store (role_permission_overrides); App\Services\Auth\PermissionService resolves the effective set, and the
 * Gate answers `$user->can('orders.cancel', $order)` with it (branch scoping included).
 */
enum Permission: string
{
    case ORDERS_VIEW = 'orders.view';
    case ORDERS_MANAGE = 'orders.manage';
    case ORDERS_CANCEL = 'orders.cancel';
    case ORDERS_DISCOUNT = 'orders.discount';
    case ORDERS_VOID_ITEM = 'orders.void_item';
    case ORDERS_ASSIGN_COURIER = 'orders.assign_courier';
    case ORDERS_REFUND = 'orders.refund';
    case KITCHEN_MANAGE = 'kitchen.manage';
    case DELIVERIES_MANAGE = 'deliveries.manage';
    case TABLES_MANAGE = 'tables.manage';
    case MENU_VIEW = 'menu.view';
    case MENU_MANAGE = 'menu.manage';
    case STOPLIST_MANAGE = 'stoplist.manage';
    // P1-21: open, top up and close batches (kazan stock) of a branch
    case BATCHES_MANAGE = 'batches.manage';
    case STAFF_VIEW = 'staff.view';
    case STAFF_MANAGE = 'staff.manage';
    case STAFF_PIN = 'staff.pin';
    case DEVICES_VIEW = 'devices.view';
    case DEVICES_MANAGE = 'devices.manage';
    case REPORTS_VIEW = 'reports.view';
    case FINANCE_VIEW = 'finance.view';
    case FINANCE_MANAGE = 'finance.manage';
    case SETTINGS_MANAGE = 'settings.manage';
    // P1-24: draw delivery zones and set their prices
    case ZONES_MANAGE = 'zones.manage';
    case AUDIT_VIEW = 'audit.view';
    case PERMISSIONS_MANAGE = 'permissions.manage';
    case POS_OPEN_SHIFT = 'pos.open_shift';
    case POS_CLOSE_SHIFT = 'pos.close_shift';
    case POS_CASH_IN_OUT = 'pos.cash_in_out';
    case POS_TAKE_PAYMENT = 'pos.take_payment';
    case POS_REPRINT_BILL = 'pos.reprint_bill';

    public function label(): string
    {
        return match ($this) {
            self::ORDERS_VIEW => "Buyurtmalarni ko'rish",
            self::ORDERS_MANAGE => 'Buyurtma yaratish va holatini o\'zgartirish',
            self::ORDERS_CANCEL => 'Buyurtmani bekor qilish yoki rad etish',
            self::ORDERS_DISCOUNT => 'Chegirma berish',
            self::ORDERS_VOID_ITEM => "Taomni chekdan o'chirish",
            self::ORDERS_ASSIGN_COURIER => 'Kuryer biriktirish',
            self::ORDERS_REFUND => 'Pulni qaytarish',
            self::KITCHEN_MANAGE => 'Oshxona ekrani: "tayyor" belgilash',
            self::DELIVERIES_MANAGE => 'Yetkazib berish (kuryer)',
            self::TABLES_MANAGE => 'Stollarni boshqarish',
            self::MENU_VIEW => "Menyuni ko'rish",
            self::MENU_MANAGE => 'Menyu va narxlarni tahrirlash',
            self::STOPLIST_MANAGE => 'Stop-list (tugadi belgisi)',
            self::BATCHES_MANAGE => 'Qozon va partiya qoldig\'i',
            self::STAFF_VIEW => "Xodimlarni ko'rish",
            self::STAFF_MANAGE => 'Xodimlar va rollarni boshqarish',
            self::STAFF_PIN => "Xodim PIN kodini o'rnatish",
            self::DEVICES_VIEW => "Qurilmalarni ko'rish",
            self::DEVICES_MANAGE => 'Qurilmalarni ulash va o\'chirish',
            self::REPORTS_VIEW => 'Hisobotlar',
            self::FINANCE_VIEW => "Moliyani ko'rish",
            self::FINANCE_MANAGE => "Pul yechish va to'ldirish",
            self::SETTINGS_MANAGE => "Do'kon sozlamalari",
            self::ZONES_MANAGE => 'Yetkazib berish hududlari va narxlari',
            self::AUDIT_VIEW => 'Harakatlar jurnali',
            self::PERMISSIONS_MANAGE => "Ruxsatlarni o'zgartirish",
            self::POS_OPEN_SHIFT => 'Smenani ochish',
            self::POS_CLOSE_SHIFT => 'Smenani yopish',
            self::POS_CASH_IN_OUT => 'Kassaga pul kiritish va chiqarish',
            self::POS_TAKE_PAYMENT => "To'lov qabul qilish",
            self::POS_REPRINT_BILL => 'Schyotni qayta chiqarish',
        };
    }

    /**
     * Whether an owner may grant it to another role through a per-store override. Money leaving the platform, the
     * store's settings, staff/role management, the audit log and the permission screen itself stay with the owner.
     */
    public function isGrantable(): bool
    {
        return !in_array($this, [
            self::ORDERS_REFUND,
            self::FINANCE_MANAGE,
            self::SETTINGS_MANAGE,
            self::ZONES_MANAGE,
            self::STAFF_MANAGE,
            self::AUDIT_VIEW,
            self::PERMISSIONS_MANAGE,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }
}
