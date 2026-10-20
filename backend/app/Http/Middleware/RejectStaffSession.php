<?php

namespace App\Http\Middleware;

use App\Services\Auth\TokenService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `not-staff-session`: the route is not for a staff PIN session on a terminal. Owners and managers may PIN in on a
 * POS/KDS terminal for order work, but money, settings, permissions, staff and device administration, the audit log
 * and account-wide actions (logout-all, phone change) need their own sign-in (Mini App, app, panel).
 */
class RejectStaffSession
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && self::isStaffSession($token)) {
            return response()->json([
                'success' => false,
                'message' => "Bu amal terminaldagi xodim sessiyasida mavjud emas. Ilova yoki panel orqali kiring.",
                'data' => ['reason' => 'staff_session_not_allowed'],
            ], 403);
        }

        return $next($request);
    }

    public static function isStaffSession(PersonalAccessToken $token): bool
    {
        // By name too: sessions opened before tokens got the narrow ability carry ['*']
        return $token->name === TokenService::STAFF_SESSION_NAME
            || in_array(TokenService::STAFF_SESSION_ABILITY, (array) $token->abilities, true);
    }
}
