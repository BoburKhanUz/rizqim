<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
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

    public function view(User $user, Product $product): bool
    {
        return $user->store_id === $product->store_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [\App\Enums\UserRole::OWNER, \App\Enums\UserRole::MANAGER]);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->store_id === $product->store_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->store_id === $product->store_id;
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->store_id === $product->store_id;
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->store_id === $product->store_id;
    }
}
