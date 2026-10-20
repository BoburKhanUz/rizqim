<?php

namespace App\Services\Auth;

use App\Enums\Permission as P;
use App\Enums\UserRole;

/**
 * The default role -> permission map. A store may adjust it (role_permission_overrides, PermissionService), except
 * for the owner, whose set is fixed so nobody can lock the store out of its own settings. super_admin has every
 * permission everywhere. Changing this map changes what existing staff may do: keep owner/manager/courier as the API
 * and panel have always behaved (OrderPolicy, ProductPolicy run on it).
 */
final class RolePermissions
{
    /**
     * @return array<string, list<P>> role value => permissions
     */
    public static function defaults(): array
    {
        return [
            UserRole::OWNER->value => array_values(array_filter(P::cases(), fn (P $permission) => $permission !== P::ORDERS_REFUND)),
            UserRole::MANAGER->value => [
                P::ORDERS_VIEW, P::ORDERS_MANAGE, P::ORDERS_CANCEL, P::ORDERS_DISCOUNT, P::ORDERS_VOID_ITEM,
                P::ORDERS_ASSIGN_COURIER, P::KITCHEN_MANAGE, P::TABLES_MANAGE, P::MENU_VIEW, P::STOPLIST_MANAGE,
                P::STAFF_VIEW, P::STAFF_PIN, P::DEVICES_VIEW, P::DEVICES_MANAGE, P::REPORTS_VIEW,
                P::POS_OPEN_SHIFT, P::POS_CLOSE_SHIFT, P::POS_CASH_IN_OUT, P::POS_TAKE_PAYMENT, P::POS_REPRINT_BILL,
            ],
            // Closes bills and takes payment; cancelling, discounts and voids need a manager
            UserRole::CASHIER->value => [
                P::ORDERS_VIEW, P::ORDERS_MANAGE, P::TABLES_MANAGE, P::MENU_VIEW,
                P::POS_OPEN_SHIFT, P::POS_CLOSE_SHIFT, P::POS_TAKE_PAYMENT,
            ],
            UserRole::WAITER->value => [
                P::ORDERS_VIEW, P::ORDERS_MANAGE, P::TABLES_MANAGE, P::MENU_VIEW,
            ],
            // Only the kitchen screen's "ready" button
            UserRole::COOK->value => [
                P::ORDERS_VIEW, P::KITCHEN_MANAGE, P::MENU_VIEW,
            ],
            UserRole::COURIER->value => [
                P::DELIVERIES_MANAGE,
            ],
            UserRole::CUSTOMER->value => [],
        ];
    }

    /**
     * @return list<P>
     */
    public static function for(UserRole $role): array
    {
        if ($role === UserRole::SUPER_ADMIN) {
            return P::cases();
        }

        return self::defaults()[$role->value] ?? [];
    }

    /**
     * Roles whose permissions a store may override.
     *
     * @return list<UserRole>
     */
    public static function overridableRoles(): array
    {
        return [UserRole::MANAGER, UserRole::CASHIER, UserRole::WAITER, UserRole::COOK, UserRole::COURIER];
    }
}
