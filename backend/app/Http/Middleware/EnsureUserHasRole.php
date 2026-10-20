<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage: ->middleware('role:owner') or ->middleware('role:owner,manager').
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Avtorizatsiyadan o\'tilmagan.',
                'data' => [],
            ], 401);
        }

        $role = $user->role->value ?? ''; // empty for a user without a role: matches no route role

        if (!in_array($role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat etilmagan',
                'data' => [],
            ], 403);
        }

        return $next($request);
    }
}
