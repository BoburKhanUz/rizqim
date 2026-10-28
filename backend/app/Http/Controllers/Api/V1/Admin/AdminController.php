<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\TwoFactorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-11: shared parts of the admin API controllers (/api/v1/admin, routes/api_admin.php): the signed-in member and
 * how a member is shown.
 */
abstract class AdminController extends Controller
{
    use ApiResponse;

    protected function member(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * An error in the admin envelope: {success: false, message, code, data: {reason, ...}}.
     *
     * @param  array<string, mixed>  $data
     */
    protected function refuse(string $message, string $code, int $status, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => ['reason' => $code, ...$data],
        ], $status);
    }

    /**
     * The signed-in member as the admin app needs them (GET /admin/me, the session answers).
     *
     * @return array{id: int, name: string, email: string|null, platform_role: string|null, platform_role_label: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null}
     */
    protected function memberPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => $user->email,
            'platform_role' => $user->platform_role?->value,
            'platform_role_label' => $user->platform_role?->label(),
            'permissions' => array_map(fn (AdminPermission $permission) => $permission->value, app(AdminPermissionService::class)->permissionsFor($user)),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'recovery_codes_left' => app(TwoFactorService::class)->recoveryCodesLeft($user),
            'last_login_at' => $user->platform_last_login_at?->toIso8601String(),
        ];
    }
}
