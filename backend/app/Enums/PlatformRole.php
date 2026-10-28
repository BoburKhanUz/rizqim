<?php

namespace App\Enums;

/**
 * P4-11: the RIZQIM team's roles in the Boshqaruv markazi (platform/admin, docs/PHASE4_PLAN.md §5). A user is
 * platform staff when users.platform_role is set. What each role may do is App\Services\Admin\PlatformRolePermissions
 * (defaults) with the superadmin's overrides (platform_role_permissions); `superadmin` is fixed: everything.
 *
 * Only `superadmin` is also users.role = super_admin (the old store-level super admin, the old Filament panels); the
 * other roles are users.role = platform, which holds no store-level permission at all.
 */
enum PlatformRole: string
{
    case SUPERADMIN = 'superadmin';
    case OPERATOR = 'operator';
    case SUPPORT = 'support';
    case FINANCE = 'finance';
    case SALES = 'sales';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Superadmin',
            self::OPERATOR => 'Operator',
            self::SUPPORT => 'Yordam',
            self::FINANCE => 'Moliya',
            self::SALES => 'Savdo va ulash',
            self::VIEWER => "Faqat ko'rish",
        };
    }

    /**
     * Whether the superadmin may change the role's permissions (the superadmin's own set is fixed: all of them).
     */
    public function isEditable(): bool
    {
        return $this !== self::SUPERADMIN;
    }

    /**
     * The users.role that goes with the platform role.
     */
    public function userRole(): UserRole
    {
        return $this === self::SUPERADMIN ? UserRole::SUPER_ADMIN : UserRole::PLATFORM;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
