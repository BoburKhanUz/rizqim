<?php

namespace App\Enums;

/**
 * P4-11: what a RIZQIM team member may do in the Boshqaruv markazi (admin API /api/v1/admin, `admin.can:` middleware).
 * Separate from the store-level App\Enums\Permission: an admin permission never lets anyone act inside a store as its
 * staff. Defaults per platform role: App\Services\Admin\PlatformRolePermissions; the superadmin may change them.
 *
 * Stage B sections (Bugun, Buyurtmalar, Monitoring...) use the entries that are already here.
 */
enum AdminPermission: string
{
    case TODAY_VIEW = 'admin.today.view';
    case ORDERS_VIEW = 'admin.orders.view';
    // Cancel, fix the status, message the store or the customer
    case ORDERS_INTERVENE = 'admin.orders.intervene';
    case ORDERS_REFUND_REQUEST = 'admin.orders.refund_request';
    case ORDERS_REFUND_APPROVE = 'admin.orders.refund_approve';
    case STORES_VIEW = 'admin.stores.view';
    case STORES_MANAGE = 'admin.stores.manage';
    case STORES_IMPERSONATE = 'admin.stores.impersonate';
    case FINANCE_VIEW = 'admin.finance.view';
    case FINANCE_MANAGE = 'admin.finance.manage';
    case PAYOUTS_APPROVE = 'admin.payouts.approve';
    case SUBSCRIPTIONS_MANAGE = 'admin.subscriptions.manage';
    case DEVICES_VIEW = 'admin.devices.view';
    case MONITORING_VIEW = 'admin.monitoring.view';
    // Retry or delete failed jobs, acknowledge and resolve alerts, alert rules
    case MONITORING_MANAGE = 'admin.monitoring.manage';
    case SUPPORT_VIEW = 'admin.support.view';
    case SUPPORT_MANAGE = 'admin.support.manage';
    case USERS_VIEW = 'admin.users.view';
    // The Marketing section read-only (Faqat ko'rish sees it)
    case MARKETING_VIEW = 'admin.marketing.view';
    case MARKETING_MANAGE = 'admin.marketing.manage';
    case INTEGRATIONS_VIEW = 'admin.integrations.view';
    case INTEGRATIONS_MANAGE = 'admin.integrations.manage';
    case SYSTEM_MANAGE = 'admin.system.manage';
    case TEAM_MANAGE = 'admin.team.manage';
    case AUDIT_VIEW = 'admin.audit.view';
    // Full phone and card numbers; every reveal is audited (App\Services\Admin\AdminPii)
    case PII_VIEW = 'admin.pii.view';
    case EXPORT = 'admin.export';

    public function label(): string
    {
        return match ($this) {
            self::TODAY_VIEW => "Bugungi holatni ko'rish",
            self::ORDERS_VIEW => "Buyurtmalarni ko'rish",
            self::ORDERS_INTERVENE => "Buyurtmaga aralashish (bekor qilish, holatni tuzatish, xabar yozish)",
            self::ORDERS_REFUND_REQUEST => "Pulni qaytarishni so'rash",
            self::ORDERS_REFUND_APPROVE => 'Pulni qaytarishni tasdiqlash',
            self::STORES_VIEW => "Ovqatxonalarni ko'rish",
            self::STORES_MANAGE => 'Ovqatxonalarni boshqarish',
            self::STORES_IMPERSONATE => 'Ovqatxona egasi sifatida kirish',
            self::FINANCE_VIEW => "Moliyani ko'rish",
            self::FINANCE_MANAGE => "Moliya: qo'lda tuzatish va hisobotlar",
            self::PAYOUTS_APPROVE => "Pul yechishni tasdiqlash",
            self::SUBSCRIPTIONS_MANAGE => 'Obunalar, tariflar va hisob-fakturalar',
            self::DEVICES_VIEW => "Qurilmalarni ko'rish",
            self::MONITORING_VIEW => "Monitoringni ko'rish",
            self::MONITORING_MANAGE => 'Monitoring: fon vazifalari va ogohlantirishlar',
            self::SUPPORT_VIEW => "Murojaatlarni ko'rish",
            self::SUPPORT_MANAGE => 'Murojaatlarni hal qilish',
            self::USERS_VIEW => "Foydalanuvchilarni ko'rish",
            self::MARKETING_VIEW => "Marketingni ko'rish",
            self::MARKETING_MANAGE => 'Marketing: aksiyalar, promo-kodlar, bannerlar',
            self::INTEGRATIONS_VIEW => "Integratsiyalarni ko'rish",
            self::INTEGRATIONS_MANAGE => 'Integratsiyalarni boshqarish',
            self::SYSTEM_MANAGE => 'Tizim sozlamalari',
            self::TEAM_MANAGE => 'Jamoa va rollar',
            self::AUDIT_VIEW => 'Harakatlar jurnali',
            self::PII_VIEW => "To'liq telefon va karta raqamlarini ko'rish",
            self::EXPORT => 'Eksport (Excel, CSV)',
        };
    }

    /**
     * The admin app section the permission belongs to (the Rollar matrix groups rows by it).
     */
    public function group(): string
    {
        return match ($this) {
            self::TODAY_VIEW => 'Bugun',
            self::ORDERS_VIEW, self::ORDERS_INTERVENE, self::ORDERS_REFUND_REQUEST, self::ORDERS_REFUND_APPROVE => 'Buyurtmalar',
            self::STORES_VIEW, self::STORES_MANAGE, self::STORES_IMPERSONATE => 'Ovqatxonalar',
            self::SUBSCRIPTIONS_MANAGE, self::DEVICES_VIEW => 'POS va obunalar',
            self::FINANCE_VIEW, self::FINANCE_MANAGE, self::PAYOUTS_APPROVE => 'Moliya',
            self::SUPPORT_VIEW, self::SUPPORT_MANAGE, self::USERS_VIEW => 'Mijozlar va yordam',
            self::MARKETING_VIEW, self::MARKETING_MANAGE => 'Marketing',
            self::MONITORING_VIEW, self::MONITORING_MANAGE => 'Monitoring',
            self::INTEGRATIONS_VIEW, self::INTEGRATIONS_MANAGE => 'Integratsiyalar',
            self::SYSTEM_MANAGE, self::TEAM_MANAGE, self::AUDIT_VIEW, self::PII_VIEW, self::EXPORT => 'Tizim',
        };
    }

    /**
     * A read-only permission (Faqat ko'rish gets these). pii.view is not one: it reveals personal data.
     */
    public function isView(): bool
    {
        return $this !== self::PII_VIEW && str_ends_with($this->value, '.view');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }
}
