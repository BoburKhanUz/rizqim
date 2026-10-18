<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BranchPolicy
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

    public function view(User $user, Branch $branch): bool
    {
        if ($user->role === \App\Enums\UserRole::MANAGER) {
            return $user->store_id === $branch->store_id && $user->branch_id === $branch->id;
        }
        return $user->store_id === $branch->store_id;
    }

    public function create(User $user): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER;
    }

    public function update(User $user, Branch $branch): bool
    {
        if ($user->role === \App\Enums\UserRole::MANAGER) {
            return $user->store_id === $branch->store_id && $user->branch_id === $branch->id;
        }
        return $user->store_id === $branch->store_id;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER && $user->store_id === $branch->store_id;
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER && $user->store_id === $branch->store_id;
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->role === \App\Enums\UserRole::OWNER && $user->store_id === $branch->store_id;
    }
}
