<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === \App\Enums\UserRole::SUPER_ADMIN) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [\App\Enums\UserRole::OWNER, \App\Enums\UserRole::MANAGER]);
    }

    public function view(User $user, User $model): bool
    {
        return $user->store_id === $model->store_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [\App\Enums\UserRole::OWNER, \App\Enums\UserRole::MANAGER]);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->store_id !== $model->store_id) return false;
        
        if ($user->role === \App\Enums\UserRole::MANAGER) {
            // Manager can only update couriers (and the floor staff: cashier, waiter, cook) belonging to their branch
            return in_array($model->role, [
                \App\Enums\UserRole::COURIER,
                \App\Enums\UserRole::CASHIER,
                \App\Enums\UserRole::WAITER,
                \App\Enums\UserRole::COOK,
            ], true) && $user->branch_id === $model->branch_id;
        }
        
        return in_array($user->role, [\App\Enums\UserRole::OWNER]);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function restore(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }
}
