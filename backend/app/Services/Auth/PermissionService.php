<?php

namespace App\Services\Auth;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\RolePermissionOverride;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves what a user may do: the role's defaults (RolePermissions) with the store's overrides applied, then
 * scoped to the user's store and, for staff tied to a branch, to that branch. The Gate asks it for every ability that
 * is a Permission value, so `$user->can('orders.cancel', $order)` and the `permission:` route middleware work.
 */
class PermissionService
{
    private const CACHE_TTL = 600;

    /**
     * Effective permissions of the user (in Permission::cases() order).
     *
     * @return list<Permission>
     */
    public function permissionsFor(User $user): array
    {
        $role = $user->role ?? null;
        if (!$role instanceof UserRole) {
            return [];
        }

        if ($role === UserRole::SUPER_ADMIN) {
            return Permission::cases();
        }

        // Store roles without a store have nothing (EnsureStaffHasStore already refuses such accounts)
        if ($role->isStoreStaff() && $user->store_id === null) {
            return [];
        }

        return $this->forRole($role, $user->store_id !== null ? (int) $user->store_id : null);
    }

    /**
     * A role's permissions in a store (null: the defaults).
     *
     * @return list<Permission>
     */
    public function forRole(UserRole $role, ?int $storeId): array
    {
        $granted = array_map(fn (Permission $permission) => $permission->value, RolePermissions::for($role));

        if ($storeId !== null && in_array($role, RolePermissions::overridableRoles(), true)) {
            foreach ($this->overrides($storeId)[$role->value] ?? [] as $permission => $allowed) {
                $granted = $allowed
                    ? array_unique([...$granted, $permission])
                    : array_diff($granted, [$permission]);
            }
        }

        return array_values(array_filter(Permission::cases(), fn (Permission $permission) => in_array($permission->value, $granted, true)));
    }

    public function has(User $user, Permission $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user), true);
    }

    /**
     * Whether the user holds the permission for the subject: a Store, a Branch, or any model with a store_id (and,
     * when it has the column, a branch_id). No subject = "anywhere in the user's own store".
     */
    public function allows(User $user, Permission $permission, mixed $subject = null): bool
    {
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        if (!$this->has($user, $permission)) {
            return false;
        }

        if (!$subject instanceof Model) {
            return true;
        }

        [$storeId, $branchId, $hasBranch] = $this->locate($subject);

        if ($user->store_id === null || $storeId === null || (int) $user->store_id !== $storeId) {
            return false;
        }

        return !$hasBranch || !$this->isBranchScoped($user) || (int) $user->branch_id === $branchId;
    }

    /**
     * Staff tied to one branch act only there; owners (and staff without a branch) act store-wide.
     */
    public function isBranchScoped(User $user): bool
    {
        return $user->branch_id !== null && !in_array($user->role, [UserRole::OWNER, UserRole::SUPER_ADMIN], true);
    }

    /**
     * The full matrix for a store (null: defaults), for the permission screens: role => [permission => bool].
     *
     * @return array<string, array<string, bool>>
     */
    public function matrix(?int $storeId): array
    {
        $matrix = [];
        foreach ([UserRole::OWNER, ...RolePermissions::overridableRoles()] as $role) {
            $granted = $this->forRole($role, $storeId);
            foreach (Permission::cases() as $permission) {
                $matrix[$role->value][$permission->value] = in_array($permission, $granted, true);
            }
        }

        return $matrix;
    }

    /**
     * Grant (true), take away (false) or reset to the default (null) a permission for a role in a store. Returns
     * the role's previous and new state of that permission.
     *
     * @return array{before: bool, after: bool}
     */
    public function setOverride(Store $store, UserRole $role, Permission $permission, ?bool $allowed, ?User $actor = null): array
    {
        if (!in_array($role, RolePermissions::overridableRoles(), true)) {
            throw new \InvalidArgumentException("The {$role->value} role's permissions cannot be changed.");
        }
        if ($allowed === true && !$permission->isGrantable()) {
            throw new \InvalidArgumentException("{$permission->value} cannot be granted to other roles.");
        }

        $before = in_array($permission, $this->forRole($role, (int) $store->id), true);
        $default = in_array($permission, RolePermissions::for($role), true);

        if ($allowed === null || $allowed === $default) {
            RolePermissionOverride::where('store_id', $store->id)
                ->where('role', $role->value)
                ->where('permission', $permission->value)
                ->get()
                ->each->delete(); // model events clear the cache
        } else {
            RolePermissionOverride::updateOrCreate(
                ['store_id' => $store->id, 'role' => $role->value, 'permission' => $permission->value],
                ['allowed' => $allowed, 'updated_by' => $actor?->id],
            );
        }

        return ['before' => $before, 'after' => in_array($permission, $this->forRole($role, (int) $store->id), true)];
    }

    public static function forgetStore(int $storeId): void
    {
        Cache::forget(self::cacheKey($storeId));
    }

    /**
     * @return array<string, array<string, bool>> role => [permission => allowed]
     */
    private function overrides(int $storeId): array
    {
        return Cache::remember(self::cacheKey($storeId), self::CACHE_TTL, function () use ($storeId) {
            $overrides = [];
            foreach (RolePermissionOverride::where('store_id', $storeId)->get(['role', 'permission', 'allowed']) as $override) {
                if (Permission::tryFrom($override->permission)) {
                    $overrides[$override->role][$override->permission] = (bool) $override->allowed;
                }
            }

            return $overrides;
        });
    }

    private static function cacheKey(int $storeId): string
    {
        return "permissions:overrides:store_{$storeId}";
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: bool} store id, branch id, whether the subject is tied to a branch at all
     */
    private function locate(Model $subject): array
    {
        if ($subject instanceof Store) {
            return [(int) $subject->getKey(), null, false];
        }

        if ($subject instanceof Branch) {
            return [(int) $subject->store_id, (int) $subject->getKey(), true];
        }

        $storeId = $subject->getAttribute('store_id');
        $hasBranch = array_key_exists('branch_id', $subject->getAttributes());
        $branchId = $subject->getAttribute('branch_id');

        return [
            $storeId !== null ? (int) $storeId : null,
            $branchId !== null ? (int) $branchId : null,
            $hasBranch,
        ];
    }
}
