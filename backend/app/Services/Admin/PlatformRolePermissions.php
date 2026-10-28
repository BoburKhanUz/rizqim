<?php

namespace App\Services\Admin;

use App\Enums\AdminPermission as P;
use App\Enums\PlatformRole;

/**
 * P4-11: the default platform role -> admin permission map (docs/PHASE4_PLAN.md §5). The superadmin may change any
 * role's set except their own (platform_role_permissions, AdminPermissionService); superadmin always has everything.
 */
final class PlatformRolePermissions
{
    /**
     * @return list<P>
     */
    public static function for(PlatformRole $role): array
    {
        return match ($role) {
            PlatformRole::SUPERADMIN => P::cases(),
            // Bugun, orders with intervention, stores (read), monitoring
            PlatformRole::OPERATOR => [
                P::TODAY_VIEW, P::ORDERS_VIEW, P::ORDERS_INTERVENE, P::ORDERS_REFUND_REQUEST, P::STORES_VIEW,
                P::DEVICES_VIEW, P::MONITORING_VIEW, P::MONITORING_MANAGE, P::INTEGRATIONS_VIEW,
            ],
            // Complaints, users, orders (read); asks for refunds, finance approves them
            PlatformRole::SUPPORT => [
                P::SUPPORT_VIEW, P::SUPPORT_MANAGE, P::USERS_VIEW, P::ORDERS_VIEW, P::ORDERS_REFUND_REQUEST,
                P::STORES_VIEW,
            ],
            // Finance, subscriptions and invoices, payout approval, manual corrections, reports
            PlatformRole::FINANCE => [
                P::FINANCE_VIEW, P::FINANCE_MANAGE, P::PAYOUTS_APPROVE, P::SUBSCRIPTIONS_MANAGE, P::ORDERS_VIEW,
                P::ORDERS_REFUND_APPROVE, P::STORES_VIEW, P::EXPORT,
            ],
            // Applications, onboarding, the store card, tariff assignment
            PlatformRole::SALES => [
                P::STORES_VIEW, P::STORES_MANAGE, P::SUBSCRIPTIONS_MANAGE, P::DEVICES_VIEW,
            ],
            // Sees every section, changes nothing: no personal data in full, no export
            PlatformRole::VIEWER => array_values(array_filter(P::cases(), fn (P $permission) => $permission->isView())),
        };
    }
}
