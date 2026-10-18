<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::SUPER_ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::OWNER, UserRole::MANAGER], true);
    }

    public function view(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::OWNER, UserRole::MANAGER => $this->manages($user, $order),
            UserRole::COURIER => $this->isAssignedCourier($user, $order),
            default => (int) $order->user_id === (int) $user->id,
        };
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
     * the order assigned to them through the delivery phase. Refunds are super_admin only (before()).
     */
    public function changeStatus(User $user, Order $order, OrderStatus $to): bool
    {
        if ($to === OrderStatus::REFUNDED) {
            return false;
        }

        if ($user->role === UserRole::COURIER) {
            return $this->isAssignedCourier($user, $order)
                && in_array($order->status, OrderStatus::withCourier(), true)
                && in_array($to, [OrderStatus::ON_THE_WAY, OrderStatus::DELIVERY, OrderStatus::DELIVERED], true);
        }

        return $this->manages($user, $order);
    }

    public function assignCourier(User $user, Order $order): bool
    {
        return $this->manages($user, $order);
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
     * Owner: any order of their store. Manager: their store, narrowed to their branch when they have one.
     */
    protected function manages(User $user, Order $order): bool
    {
        if (!in_array($user->role, [UserRole::OWNER, UserRole::MANAGER], true)
            || !$user->store_id
            || (int) $user->store_id !== (int) $order->store_id) {
            return false;
        }

        return $user->role === UserRole::OWNER
            || !$user->branch_id
            || (int) $user->branch_id === (int) $order->branch_id;
    }

    protected function isAssignedCourier(User $user, Order $order): bool
    {
        return $user->role === UserRole::COURIER
            && $order->courier_id !== null
            && (int) $order->courier_id === (int) $user->id
            && (int) $user->store_id === (int) $order->store_id;
    }
}
