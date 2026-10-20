<?php

namespace App\Filament\Support;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;
use App\Services\Auth\PermissionService;

/**
 * Permission and branch helpers of the P1-21 menu screens (batches, stop-list) in the store panel: a branch-bound
 * manager works on their branch only, like the API.
 */
class MenuPanelAccess
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function has(Permission $permission): bool
    {
        $user = static::user();

        return $user !== null && StorePanelScope::storeId() !== null && app(PermissionService::class)->has($user, $permission);
    }

    /**
     * The permission on one branch of the panel's store.
     */
    public static function allowsBranch(Permission $permission, ?int $branchId): bool
    {
        $user = static::user();
        $storeId = StorePanelScope::storeId();
        if ($user === null || $storeId === null || $branchId === null) {
            return false;
        }

        $branch = Branch::where('store_id', $storeId)->find($branchId);

        return $branch !== null && app(PermissionService::class)->allows($user, $permission, $branch);
    }

    /**
     * The branch a branch-bound user works in; null for owners and store-wide staff.
     */
    public static function ownBranchId(): ?int
    {
        $user = static::user();

        return $user !== null && app(PermissionService::class)->isBranchScoped($user) ? (int) $user->branch_id : null;
    }

    /**
     * id => name of the branches the user may pick.
     *
     * @return array<int, string>
     */
    public static function branchOptions(): array
    {
        return Branch::where('store_id', StorePanelScope::storeId())
            ->when(static::ownBranchId(), fn ($query, int $branchId) => $query->whereKey($branchId))
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }
}
