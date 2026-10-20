<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    /**
     * Usage: ->middleware('permission:devices.manage') or 'permission:orders.view,reports.view' (any of them).
     * Checks the permission in the user's own store; record-level checks (branch, ownership) stay in the controller.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Avtorizatsiyadan o\'tilmagan.',
                'data' => [],
            ], 401);
        }

        foreach ($permissions as $permission) {
            $permission = Permission::tryFrom($permission);
            if ($permission && $this->permissions->has($user, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Ruxsat etilmagan',
            'data' => [],
        ], 403);
    }
}
