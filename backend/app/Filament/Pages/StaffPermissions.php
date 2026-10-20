<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Filament\Support\StorePanelScope;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RolePermissions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * "Ruxsatlar": the store's role -> permission table. The owner ticks or unticks what each role may do (stored as
 * per-store overrides of RolePermissions); everyone with staff.view sees it read-only.
 */
class StaffPermissions extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Xodimlar va ruxsatlar';
    protected static ?string $navigationLabel = 'Ruxsatlar';
    protected static ?string $title = 'Rollar va ruxsatlar';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.pages.staff-permissions';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->has($user, Permission::STAFF_VIEW);
    }

    public function canEditPermissions(): bool
    {
        $user = auth()->user();

        return $user instanceof User && StorePanelScope::store() !== null
            && app(PermissionService::class)->allows($user, Permission::PERMISSIONS_MANAGE, StorePanelScope::store());
    }

    /**
     * @return list<UserRole>
     */
    public function roles(): array
    {
        return [UserRole::OWNER, ...RolePermissions::overridableRoles()];
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return Permission::cases();
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function matrix(): array
    {
        return app(PermissionService::class)->matrix(StorePanelScope::storeId());
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function defaults(): array
    {
        return app(PermissionService::class)->matrix(null);
    }

    public function toggle(string $role, string $permission): void
    {
        $store = StorePanelScope::store();
        $user = auth()->user();
        $role = UserRole::tryFrom($role);
        $permission = Permission::tryFrom($permission);

        abort_unless($store && $user instanceof User && $role && $permission && $this->canEditPermissions(), 403);

        $current = $this->matrix()[$role->value][$permission->value] ?? false;
        $default = in_array($permission, RolePermissions::for($role), true);

        if (!$current && !$permission->isGrantable()) {
            Notification::make()->title("Bu ruxsatni faqat do'kon egasi bajaradi.")->warning()->send();

            return;
        }

        try {
            // Back to the default when the new state is the default, so the default map keeps applying later
            $change = app(PermissionService::class)->setOverride($store, $role, $permission, !$current === $default ? null : !$current, $user);
        } catch (\InvalidArgumentException) {
            Notification::make()->title("Bu ruxsatni o'zgartirib bo'lmaydi.")->warning()->send();

            return;
        }

        if ($change['before'] !== $change['after']) {
            app(AuditLogger::class)->record('permissions.changed', $store, ['allowed' => $change['before']], ['allowed' => $change['after']], [
                'role' => $role->value,
                'permission' => $permission->value,
            ], ['actor' => $user, 'store_id' => $store->id, 'branch_id' => null]);
        }

        Notification::make()->title('Saqlandi')->success()->send();
    }
}
