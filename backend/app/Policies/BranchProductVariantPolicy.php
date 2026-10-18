<?php

namespace App\Policies;

use App\Models\BranchProductVariant;
use App\Models\User;

class BranchProductVariantPolicy
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

    public function view(User $user, BranchProductVariant $bpv): bool
    {
        // Branch must belong to store
        if ($user->store_id !== $bpv->branch->store_id) {
            return false;
        }

        if ($user->role === \App\Enums\UserRole::MANAGER) {
            return $user->branch_id === $bpv->branch_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [\App\Enums\UserRole::OWNER, \App\Enums\UserRole::MANAGER]);
    }

    public function update(User $user, BranchProductVariant $bpv): bool
    {
        return $this->view($user, $bpv);
    }

    public function delete(User $user, BranchProductVariant $bpv): bool
    {
        return $this->view($user, $bpv);
    }
}
