<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPermissionService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-11: Tizim → Rollar (admin.team.manage): which admin permissions each team role holds. The superadmin column is
 * fixed (everything); every other role's set can be changed (like the partner web's "Huquqlar" page).
 */
#[Group('Admin')]
class AdminRoleController extends AdminController
{
    public function __construct(
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * Roles and permissions
     *
     * `roles` in display order (`editable`: false for superadmin), `permissions` with their section (`group`),
     * `grants`: role => the permissions it holds now, and `defaults`: role => the built-in set before the
     * superadmin's changes (a cell differing from it is "O'zgartirilgan"; PUT with `allowed: null` resets it).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{roles: list<array{key: string, label: string, editable: bool}>, permissions: list<array{key: string, label: string, group: string}>, grants: array<string, list<string>>, defaults: array<string, list<string>>}}')]
    public function index(): JsonResponse
    {
        return $this->success($this->matrix());
    }

    /**
     * Change a role's permission
     *
     * `allowed`: true grants, false takes away, null resets to the role's default. The superadmin role cannot be
     * changed (422 `role_not_editable`). Needs step-up. Answers with the whole matrix.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{roles: list<array{key: string, label: string, editable: bool}>, permissions: list<array{key: string, label: string, group: string}>, grants: array<string, list<string>>, defaults: array<string, list<string>>}}')]
    #[ApiDocResponse(422, 'Superadmin is fixed (role_not_editable) or invalid input', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(PlatformRole::class)],
            'permission' => ['required', Rule::enum(AdminPermission::class)],
            'allowed' => 'present|nullable|boolean',
        ], UzValidation::messages());

        $role = PlatformRole::from($data['role']);
        $permission = AdminPermission::from($data['permission']);

        if (!$role->isEditable()) {
            return $this->refuse("Superadmin roli o'zgarmaydi: unda hamma ruxsat bor.", 'role_not_editable', 422);
        }

        $allowed = $data['allowed'] === null ? null : (bool) $data['allowed'];
        $change = $this->permissions->setOverride($role, $permission, $allowed, $this->member($request));

        $audit = AdminAudit::for($request)->changes(['allowed' => $change['before']], ['allowed' => $change['after']])
            ->meta(['role' => $role->value, 'permission' => $permission->value, 'reset_to_default' => $allowed === null]);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->matrix(), 'Ruxsat saqlandi.');
    }

    /**
     * @return array{roles: list<array{key: string, label: string, editable: bool}>, permissions: list<array{key: string, label: string, group: string}>, grants: array<string, list<string>>, defaults: array<string, list<string>>}
     */
    private function matrix(): array
    {
        return [
            'roles' => array_map(fn (PlatformRole $role) => [
                'key' => $role->value,
                'label' => $role->label(),
                'editable' => $role->isEditable(),
            ], PlatformRole::cases()),
            'permissions' => array_map(fn (AdminPermission $permission) => [
                'key' => $permission->value,
                'label' => $permission->label(),
                'group' => $permission->group(),
            ], AdminPermission::cases()),
            'grants' => $this->permissions->grants(),
            'defaults' => $this->permissions->defaults(),
        ];
    }
}
