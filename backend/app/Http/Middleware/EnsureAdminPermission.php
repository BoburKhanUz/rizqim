<?php

namespace App\Http\Middleware;

use App\Enums\AdminPermission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `admin.can:<permission>[,<permission>...]` (P4-11): the signed-in team member holds one of the admin permissions
 * (App\Enums\AdminPermission values, with or without the `admin.` prefix). 403 `forbidden` otherwise.
 */
class EnsureAdminPermission
{
    public function __construct(
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiyadan o\'tilmagan.', 'code' => 'unauthenticated', 'data' => ['reason' => 'unauthenticated']], 401);
        }

        foreach ($permissions as $permission) {
            $permission = AdminPermission::tryFrom(str_starts_with($permission, 'admin.') ? $permission : 'admin.' . $permission);
            if ($permission !== null && $this->permissions->has($user, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => "Bu amal uchun ruxsatingiz yo'q.",
            'code' => 'forbidden',
            'data' => ['reason' => 'forbidden'],
        ], 403);
    }
}
