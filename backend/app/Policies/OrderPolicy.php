<?php

namespace App\Policies;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PermissionService;

/**
 * Store staff rights come from the permission layer (orders.view / orders.manage / orders.cancel /
 * orders.assign_courier, scoped to the store and to a branch-tied member's branch); couriers and customers keep their
 * own rules below. With the default permission map owners and managers behave exactly as before.
 */
class OrderPolicy
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::SUPER_ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->permissions->has($user, Permission::ORDERS_VIEW);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->role === UserRole::COURIER) {
            return $this->isAssignedCourier($user, $order);
        }

        // Staff see their store's (branch's) orders, as staff; everyone else only their own
        if ($this->permissions->has($user, Permission::ORDERS_VIEW)) {
            return $this->permissions->allows($user, Permission::ORDERS_VIEW, $order);
        }

        return (int) $order->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return false; // Orders are created via API
    }

    public function update(User $user, Order $order): bool
    {
        return $this->manages($user, $order);
    }

    /**
     * Owners/managers run the whole lifecycle of their store's (branch's) orders; a courier only moves
     * the order assigned to them through the delivery phase; the customer may only cancel their own order while it is
     * new and unpaid (Order::isCancellableByCustomer), and only for customer_request: $reason is the cancel reason asked
     * for, so no caller can let a customer cancel with a store's reason. Refunds are super_admin only (before()).
     */
    public function changeStatus(User $user, Order $order, OrderStatus $to, ?CancelReason $reason = null): bool
    {
        if ($to === OrderStatus::REFUNDED) {
            return false;
        }

        if ($this->isOwnCancellableOrder($user, $order, $to, $reason)) {
            return true;
        }

        if ($user->role === UserRole::COURIER) {
            return $this->isAssignedCourier($user, $order)
                && in_array($order->status, OrderStatus::withCourier(), true)
                && in_array($to, [OrderStatus::ON_THE_WAY, OrderStatus::DELIVERY, OrderStatus::DELIVERED], true);
        }

        // P2-11: the kitchen's own two moves (start cooking, ready) with kitchen.manage: a cook has no orders.manage.
        // P2-30: and ready -> preparing, a line recalled after the last bump made the order ready (only through
        // KitchenService::recall: the state machine opens it for that call alone, see OrderStatusService)
        if ($this->isKitchenMove($order, $to) && $this->permissions->allows($user, Permission::KITCHEN_MANAGE, $order)) {
            return true;
        }

        // P2-30: whoever assigns couriers marks the assigned courier's pick-up (courier_assigned -> on_the_way)
        if ($to === OrderStatus::ON_THE_WAY && $order->status === OrderStatus::COURIER_ASSIGNED && $order->courier_id !== null
            && $this->permissions->allows($user, Permission::ORDERS_ASSIGN_COURIER, $order)) {
            return true;
        }

        // Cancelling or rejecting is its own permission (a cashier may run orders but not cancel them)
        return $this->manages($user, $order)
            && (!$to->isCancellation() || $this->permissions->allows($user, Permission::ORDERS_CANCEL, $order));
    }

    /**
     * accepted -> preparing and preparing -> ready: what the kitchen display does (docs/API.md, P2-11); P2-30: and
     * ready -> preparing, the recall of a line after the order became ready.
     */
    public static function isKitchenMove(Order $order, OrderStatus $to): bool
    {
        return ($order->status === OrderStatus::ACCEPTED && $to === OrderStatus::PREPARING)
            || ($order->status === OrderStatus::PREPARING && $to === OrderStatus::READY)
            || ($order->status === OrderStatus::READY && $to === OrderStatus::PREPARING);
    }

    public function assignCourier(User $user, Order $order): bool
    {
        return $this->manages($user, $order) && $this->permissions->allows($user, Permission::ORDERS_ASSIGN_COURIER, $order);
    }

    /**
     * POST /orders/{uuid}/complete is the courier app's "delivered" button.
     */
    public function complete(User $user, Order $order): bool
    {
        return $this->isAssignedCourier($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * orders.manage for this order: owner — any order of their store; other staff — their store, narrowed to their
     * branch when they have one (PermissionService::allows).
     */
    protected function manages(User $user, Order $order): bool
    {
        return $user->role !== UserRole::COURIER
            && $this->permissions->allows($user, Permission::ORDERS_MANAGE, $order);
    }

    protected function isOwnCancellableOrder(User $user, Order $order, OrderStatus $to, ?CancelReason $reason): bool
    {
        return $to === OrderStatus::CANCELED
            && $reason === CancelReason::CUSTOMER_REQUEST
            && (int) $order->user_id === (int) $user->id
            && $order->isCancellableByCustomer();
    }

    protected function isAssignedCourier(User $user, Order $order): bool
    {
        return $user->role === UserRole::COURIER
            && $order->courier_id !== null
            && (int) $order->courier_id === (int) $user->id
            && (int) $user->store_id === (int) $order->store_id;
    }
}
