<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /me/permissions: what the signed-in user may do, so apps show only the buttons that will work. The server still
 * checks every action itself. P2-30: `timezone`, the store's time zone, for staff who read orders or reports.
 */
#[Group('Auth')]
class PermissionController extends Controller
{
    use ApiResponse;

    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{role: string, store_id: int|null, branch_id: int|null, branch_scoped: bool, permissions: list<string>, device_uuid: string|null, timezone: string|null}}')]
    public function show(Request $request, PermissionService $permissions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // A TransientToken (session, tests) has no device
        $deviceId = data_get($user->currentAccessToken(), 'device_id');

        return $this->success([
            'role' => $user->role->value,
            'store_id' => $user->store_id,
            'branch_id' => $user->branch_id,
            'branch_scoped' => $permissions->isBranchScoped($user),
            'permissions' => array_map(fn ($permission) => $permission->value, $permissions->permissionsFor($user)),
            'device_uuid' => $deviceId ? \App\Models\Device::whereKey($deviceId)->value('uuid') : null,
            // P2-30: the store's time zone (order times, report days) for staff who read orders or reports; null otherwise
            'timezone' => $user->store_id !== null && ($permissions->has($user, Permission::ORDERS_VIEW) || $permissions->has($user, Permission::REPORTS_VIEW))
                && ($store = \App\Models\Store::find($user->store_id)) !== null
                ? app(\App\Services\Order\SalesReportService::class)->timezone($store)
                : null,
        ]);
    }
}
