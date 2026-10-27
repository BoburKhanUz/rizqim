<?php

namespace App\Services\Admin;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Models\PlatformRolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * P4-11: what a team member may do in the admin API: their platform role's defaults (PlatformRolePermissions) with the
 * superadmin's overrides (platform_role_permissions) applied. A disabled member, or a user outside the team, has
 * nothing. The `admin.can:` middleware and GET /admin/me use it.
 */
class AdminPermissionService
{
    private const CACHE_KEY = 'admin:permissions:overrides';

    private const CACHE_TTL = 600;

    /**
     * Review F1: what makes a role's holder able to widen access or move money on their own (manage the team, see
     * personal data in full, change the system, approve refunds or payouts). Only a superadmin gives a member a role
     * carrying any of these, or changes such a member (PlatformTeamService), and only a superadmin edits the role
     * matrix at all (AdminRoleController).
     */
    public const PRIVILEGED = [
        AdminPermission::TEAM_MANAGE,
        AdminPermission::PII_VIEW,
        AdminPermission::SYSTEM_MANAGE,
        AdminPermission::ORDERS_REFUND_APPROVE,
        AdminPermission::PAYOUTS_APPROVE,
    ];

    /**
     * @return list<AdminPermission> in AdminPermission::cases() order
     */
    public function permissionsFor(User $user): array
    {
        if (!$user->isActivePlatformStaff() || $user->platform_role === null) {
            return [];
        }

        return $this->forRole($user->platform_role);
    }

    /**
     * @return list<AdminPermission>
     */
    public function forRole(PlatformRole $role): array
    {
        $granted = array_map(fn (AdminPermission $permission) => $permission->value, PlatformRolePermissions::for($role));

        if ($role->isEditable()) {
            foreach ($this->overrides()[$role->value] ?? [] as $permission => $allowed) {
                $granted = $allowed ? array_unique([...$granted, $permission]) : array_diff($granted, [$permission]);
            }
        }

        return array_values(array_filter(
            AdminPermission::cases(),
            fn (AdminPermission $permission) => in_array($permission->value, $granted, true),
        ));
    }

    /**
     * The PRIVILEGED permissions the role holds now (with the overrides).
     *
     * @return list<AdminPermission>
     */
    public function privilegedOf(PlatformRole $role): array
    {
        return array_values(array_filter($this->forRole($role), fn (AdminPermission $permission) => in_array($permission, self::PRIVILEGED, true)));
    }

    public function has(User $user, AdminPermission $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user), true);
    }

    /**
     * @return array<string, list<string>> role => granted permission values, for GET /admin/roles
     */
    public function grants(): array
    {
        $grants = [];
        foreach (PlatformRole::cases() as $role) {
            $grants[$role->value] = array_map(fn (AdminPermission $permission) => $permission->value, $this->forRole($role));
        }

        return $grants;
    }

    /**
     * @return array<string, list<string>> role => the built-in permissions before overrides (GET /admin/roles), in
     *   AdminPermission::cases() order like grants()
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach (PlatformRole::cases() as $role) {
            $built = PlatformRolePermissions::for($role);
            $defaults[$role->value] = array_values(array_map(
                fn (AdminPermission $permission) => $permission->value,
                array_filter(AdminPermission::cases(), fn (AdminPermission $permission) => in_array($permission, $built, true)),
            ));
        }

        return $defaults;
    }

    /**
     * Grant (true), take away (false) or reset to the default (null) a permission of an editable role.
     *
     * @return array{before: bool, after: bool}
     */
    public function setOverride(PlatformRole $role, AdminPermission $permission, ?bool $allowed, ?User $actor = null): array
    {
        if (!$role->isEditable()) {
            throw new \InvalidArgumentException("The {$role->value} role's permissions cannot be changed.");
        }

        $before = in_array($permission, $this->forRole($role), true);
        $default = in_array($permission, PlatformRolePermissions::for($role), true);

        if ($allowed === null || $allowed === $default) {
            PlatformRolePermission::where('platform_role', $role->value)
                ->where('permission', $permission->value)
                ->get()
                ->each->delete(); // model events clear the cache
        } else {
            PlatformRolePermission::updateOrCreate(
                ['platform_role' => $role->value, 'permission' => $permission->value],
                ['allowed' => $allowed, 'updated_by' => $actor?->id],
            );
        }

        return ['before' => $before, 'after' => in_array($permission, $this->forRole($role), true)];
    }

    public static function forgetOverrides(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, bool>> role => [permission => allowed]
     */
    private function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $overrides = [];
            foreach (PlatformRolePermission::all(['platform_role', 'permission', 'allowed']) as $override) {
                if (AdminPermission::tryFrom($override->permission) && PlatformRole::tryFrom($override->platform_role)) {
                    $overrides[$override->platform_role][$override->permission] = (bool) $override->allowed;
                }
            }

            return $overrides;
        });
    }
}
