<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === \App\Enums\UserRole::SUPER_ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [\App\Enums\UserRole::OWNER, \App\Enums\UserRole::MANAGER]);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->store_id === $order->store_id;
    }

    public function create(User $user): bool
    {
        return false; // Orders are created via API
    }

    public function update(User $user, Order $order): bool
    {
        return $user->store_id === $order->store_id;
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
}
