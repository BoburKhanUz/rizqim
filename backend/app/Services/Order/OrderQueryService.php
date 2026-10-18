<?php

namespace App\Services\Order;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrderQueryService
{
    /**
     * The orders a user's app lists, newest first: a courier's own deliveries, the store's orders for its owner,
     * the branch's (or store's) for a manager, and anyone else's own orders.
     */
    public function visibleTo(User $user): Collection
    {
        return match ($user->role) {
            // Unassigned orders carry customer contact details and couriers can't claim them
            UserRole::COURIER => Order::where('store_id', $user->store_id)
                ->where('courier_id', $user->id)
                ->with(['items.variant.product', 'user', 'branch'])
                ->latest()
                ->get(),
            UserRole::OWNER => Order::where('store_id', $user->store_id)
                ->with(['items.variant.product.category', 'user', 'courier', 'manager', 'branch', 'statusHistories'])
                ->latest()
                ->get(),
            UserRole::MANAGER => Order::where('store_id', $user->store_id)
                ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->with(['items.variant.product.category', 'user', 'courier', 'manager', 'branch', 'statusHistories'])
                ->latest()
                ->get(),
            default => $user->orders()
                ->with(['items.variant.product'])
                ->latest()
                ->get(),
        };
    }

    /**
     * The order behind a uuid, or null when it doesn't exist or this user may not see it (OrderPolicy::view),
     * so other stores'/branches'/couriers' orders look the same as missing ones.
     */
    public function findVisible(User $user, string $uuid): ?Order
    {
        if (!Str::isUuid($uuid)) {
            return null;
        }

        $order = Order::where('uuid', $uuid)->first();

        return $order && Gate::forUser($user)->allows('view', $order) ? $order : null;
    }
}
