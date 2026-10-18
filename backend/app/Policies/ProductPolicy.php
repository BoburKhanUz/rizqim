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

    /**
     * Writing the catalog is the owner's, as in the API (/owner/products is owner-only); a manager reads it, and
     * switches items on or off for their own branch through the branch-variants API.
     */
    public function create(User $user): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->ownsCatalogOf($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->ownsCatalogOf($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->ownsCatalogOf($user, $product);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $this->ownsCatalogOf($user, $product);
    }

    private function ownsCatalogOf(User $user, Product $product): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER && $user->store_id === $product->store_id;
    }
}
