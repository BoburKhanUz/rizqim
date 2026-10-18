<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffHasStore
{
    private const STORE_ROLES = [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER];

    /**
     * Storeless staff would turn where('store_id', $user->store_id) into "IS NULL", matching every customer/super admin.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->store_id === null && in_array($user->role, self::STORE_ROLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hisobingiz hech qaysi do\'konga biriktirilmagan. Administratorga murojaat qiling.',
                'data' => [],
            ], 403);
        }

        return $next($request);
    }
}
