<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StorePolicy
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

    public function view(User $user, Store $store): bool
    {
        return $user->store_id === $store->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Store $store): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER && $user->store_id === $store->id;
    }

    public function delete(User $user, Store $store): bool
    {
        return false;
    }

    public function restore(User $user, Store $store): bool
    {
        return false;
    }

    public function forceDelete(User $user, Store $store): bool
    {
        return false;
    }
}
