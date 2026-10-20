<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RolePermissions;
use App\Services\Auth\StaffPinService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff access for a store: terminal PINs (staff.pin), roles (staff.manage) and the store's permission overrides
 * (permissions.manage). Only people of the caller's own store are ever found; anyone else is a 404.
 */
#[Group('Owner: staff access', "Xodim PIN kodi, roli va do'kon ruxsatlari jadvali.")]
class StaffAccessController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PermissionService $permissions,
        protected StaffPinService $pins,
        protected AuditLogger $audit,
    ) {}

    /**
     * PUT /owner/staff/{id}/pin {pin}
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{user_id: int, staff_code: string|null, pin_set_at: string|null}}')]
    public function setPin(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['pin' => 'required|string']);

        /** @var User $actor */
        $actor = $request->user();
        $target = $this->storeUser($actor, $id);

        if (!$target || !$this->pins->canManagePin($actor, $target)) {
            return $this->error('Xodim topilmadi', [], 404);
        }

        try {
            $this->pins->setPin($target, (string) $data['pin'], $actor);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), ['errors' => ['pin' => [$e->getMessage()]]], 422);
        }

        return $this->success([
            'user_id' => $target->id,
            'staff_code' => $target->staff_code,
            'pin_set_at' => $target->pin_set_at?->toIso8601String(),
        ], "PIN o'rnatildi.");
    }

    /**
     * DELETE /owner/staff/{id}/pin: the person can no longer sign in on terminals until a new PIN is set.
     */
    public function resetPin(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = $this->storeUser($actor, $id);

        if (!$target || !$this->pins->canManagePin($actor, $target)) {
            return $this->error('Xodim topilmadi', [], 404);
        }

        $this->pins->resetPin($target, $actor);

        return $this->success(null, "PIN o'chirildi.");
    }

    /**
     * PUT /owner/staff/{id}/role {role, branch_id?}: owner only (staff.manage); never the owner's own account.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::assignableStaffRoles()))],
            'branch_id' => 'nullable|integer',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $target = $this->storeUser($actor, $id);

        if (!$target || $target->is($actor) || !$target->role->isStoreStaff() || $target->role === UserRole::OWNER
            || !$this->permissions->allows($actor, Permission::STAFF_MANAGE, $target)) {
            return $this->error('Xodim topilmadi', [], 404);
        }

        $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $target->branch_id;
        if ($branchId !== null && !Branch::where('store_id', $actor->store_id)->whereKey($branchId)->exists()) {
            return $this->error("Noto'g'ri filial tanlandi.", [], 422);
        }

        // The change is written to the audit log by the User observer (App\Providers\AccessServiceProvider)
        $target->update(['role' => $data['role'], 'branch_id' => $branchId]);

        return $this->success($target->only(['id', 'name', 'role', 'branch_id']), "Xodim roli o'zgartirildi.");
    }

    /**
     * GET /owner/permissions: the store's role -> permission matrix, with the defaults alongside.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{roles: list<array{role: string, label: string, editable: bool}>, permissions: list<array{key: string, label: string, grantable: bool}>, matrix: array<string, array<string, bool>>, defaults: array<string, array<string, bool>>}}')]
    public function permissions(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        if ($actor->store === null) {
            return $this->noStore();
        }

        return $this->success([
            'roles' => array_map(fn (UserRole $role) => [
                'role' => $role->value,
                'label' => $role->labelUz(),
                'editable' => $role !== UserRole::OWNER,
            ], [UserRole::OWNER, ...RolePermissions::overridableRoles()]),
            'permissions' => array_map(fn (Permission $permission) => [
                'key' => $permission->value,
                'label' => $permission->label(),
                'grantable' => $permission->isGrantable(),
            ], Permission::cases()),
            'matrix' => $this->permissions->matrix((int) $actor->store_id),
            'defaults' => $this->permissions->matrix(null),
        ]);
    }

    /**
     * PUT /owner/permissions {role, permission, allowed: true|false|null}: grant, take away, or back to the default.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{role: string, permission: string, allowed: bool}}')]
    public function updatePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, RolePermissions::overridableRoles()))],
            'permission' => ['required', Rule::enum(Permission::class)],
            'allowed' => 'present|nullable|boolean',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $store = $actor->store;
        if ($store === null) {
            return $this->noStore();
        }

        $role = UserRole::from($data['role']);
        $permission = Permission::from($data['permission']);
        $allowed = $data['allowed'] === null ? null : (bool) $data['allowed'];

        if ($allowed === true && !$permission->isGrantable()) {
            return $this->error("Bu ruxsatni faqat do'kon egasi bajaradi.", [], 422);
        }

        $change = $this->permissions->setOverride($store, $role, $permission, $allowed, $actor);

        if ($change['before'] !== $change['after']) {
            $this->audit->record('permissions.changed', $store, ['allowed' => $change['before']], ['allowed' => $change['after']], [
                'role' => $role->value,
                'permission' => $permission->value,
                'reset_to_default' => $allowed === null,
            ], ['actor' => $actor, 'store_id' => $actor->store_id, 'branch_id' => null]);
        }

        return $this->success([
            'role' => $role->value,
            'permission' => $permission->value,
            'allowed' => $change['after'],
        ], "Ruxsat saqlandi.");
    }

    /**
     * The permission matrix is the caller's own store's: a superadmin (no store) is refused here and changes a store's
     * permissions in the panel instead.
     */
    private function noStore(): JsonResponse
    {
        return $this->error("Ruxsatlar jadvali faqat do'kon hisobidan boshqariladi.", ['reason' => 'store_required'], 403);
    }

    private function storeUser(User $actor, int $id): ?User
    {
        if ($actor->store_id === null) {
            return null;
        }

        return User::where('store_id', $actor->store_id)->whereKey($id)->first();
    }
}
