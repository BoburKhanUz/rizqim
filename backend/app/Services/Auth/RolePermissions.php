<?php

namespace App\Services\Auth;

use App\Enums\Permission as P;
use App\Enums\UserRole;

/**
 * The default role -> permission map. A store may adjust it (role_permission_overrides, PermissionService), except
 * for the owner, whose set is fixed so nobody can lock the store out of its own settings. super_admin has every
 * permission everywhere. Changing this map changes what existing staff may do: keep owner/manager/courier as the API
 * and the panels have always behaved (OrderPolicy and the API's permission checks run on it).
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
                P::BATCHES_MANAGE, // P1-21
                P::BRANCHES_MANAGE, // P2-12: their own branch's hours and details
                P::BOOKINGS_MANAGE, P::CUSTOMER_ACCOUNTS_MANAGE, // P3-17
                P::POS_REFUND, // P3-18: refunds within the shift (cashiers ask a manager's PIN)
                P::ORDERS_TRANSFER, P::HOSTESS_MANAGE, // P6-24/P6-26
                P::STAFF_PAY_VIEW, // P6-23: sees the work sessions and pay statements; the rules and payouts stay the owner's (grantable)
                P::TIPS_MANAGE, // P6-22: corrects a tip with a reason, splits and closes the tip pool
            ],
            // Closes bills and takes payment; cancelling, refunds and voids need a manager. A discount up to the
            // branch's pos_settings.cashier_discount_max_percent comes with pos.take_payment (P3-37, PosOrderService)
            UserRole::CASHIER->value => [
                P::ORDERS_VIEW, P::ORDERS_MANAGE, P::TABLES_MANAGE, P::MENU_VIEW,
                P::POS_OPEN_SHIFT, P::POS_CLOSE_SHIFT, P::POS_TAKE_PAYMENT,
                P::BOOKINGS_MANAGE, // P3-17: takes bookings and deposits at the till
                P::POS_REPRINT_BILL, P::POS_CASH_IN_OUT, // P3-37 (owner's decision 24.10.2026): a bill copy, cash in and out alone
            ],
            UserRole::WAITER->value => [
                P::ORDERS_VIEW, P::ORDERS_MANAGE, P::TABLES_MANAGE, P::MENU_VIEW,
            ],
            // Only the kitchen screen's "ready" button
            UserRole::COOK->value => [
                P::ORDERS_VIEW, P::KITCHEN_MANAGE, P::MENU_VIEW,
                P::BATCHES_MANAGE, // P1-21: the cook opens and closes the kazan
            ],
            // P6-24: books tables, keeps the waitlist and marks table states; sees orders and the menu, takes no payment
            UserRole::HOSTESS->value => [
                P::ORDERS_VIEW, P::TABLES_MANAGE, P::MENU_VIEW, P::BOOKINGS_MANAGE, P::HOSTESS_MANAGE,
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
        return [UserRole::MANAGER, UserRole::CASHIER, UserRole::WAITER, UserRole::COOK, UserRole::HOSTESS, UserRole::COURIER];
    }
}
