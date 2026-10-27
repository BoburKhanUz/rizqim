<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\AdminSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `admin.recent` (P4-11, step-up): the current admin session confirmed a fresh TOTP code in the last 10 minutes
 * (POST /admin/auth/confirm). Otherwise 403 `reauth_required`: the admin app asks for the code and retries once.
 * Used by team and role changes, 2FA resets, recovery codes, revoking another session; stage B adds money and
 * order interventions.
 */
class RequireAdminReauth
{
    public function __construct(
        private readonly AdminSessionService $sessions,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $this->sessions->current($user) : null;

        if ($token === null || $this->sessions->reauthenticatedUntil($token) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Bu amal uchun 2FA kodini qayta kiriting.',
                'code' => 'reauth_required',
                'data' => ['reason' => 'reauth_required'],
            ], 403);
        }

        return $next($request);
    }
}
