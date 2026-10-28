<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\Guard;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * P4-11: the `admin` guard of the admin API (/api/v1/admin, `auth:admin`). An admin session is a Sanctum token row
 * named `admin` whose only ability is `admin` (AdminSessionService), opened by email + password + TOTP. It is valid
 * for 12 hours from sign-in and dies after 60 minutes without a request (admin.session), and only for an active team
 * member with 2FA on. Unlike Sanctum's guard it never falls back to a web session user, and nothing else passes here:
 * a Mini App, app, staff PIN session or terminal token is refused (their abilities are not exactly ['admin']).
 *
 * The other way round, AccessServiceProvider makes the `sanctum` and `device` guards refuse admin tokens (and any
 * token of a non-superadmin team member), so an admin session never acts as a store owner or a customer.
 */
class AdminTokenGuard extends Guard
{
    public const NAME = 'admin';
    public const ABILITY = 'admin';

    public function __invoke(Request $request)
    {
        $token = $this->getTokenFromRequest($request);
        if (!$token) {
            return null;
        }

        $model = Sanctum::$personalAccessTokenModel;
        $accessToken = $model::findToken($token);

        if (!$accessToken instanceof PersonalAccessToken || !self::isAdminToken($accessToken)) {
            return null;
        }

        if (self::hasExpired($accessToken)) {
            // Absolute or idle limit reached: the session row goes (it is no longer listed in Sessiyalarim)
            $accessToken->delete();

            return null;
        }

        $user = $accessToken->tokenable;
        if (!$user instanceof User || !$user->isActivePlatformStaff() || !$user->hasTwoFactorEnabled()) {
            return null;
        }

        $user->withAccessToken($accessToken);
        event(new TokenAuthenticated($accessToken));

        // Idle tracking, at most one write a minute
        if ($accessToken->last_used_at === null || $accessToken->last_used_at->lt(now()->subMinute())) {
            $this->updateLastUsedAt($accessToken);
        }

        return $user;
    }

    public static function isAdminToken(PersonalAccessToken $token): bool
    {
        return $token->name === self::NAME && (array) $token->abilities === [self::ABILITY];
    }

    public static function hasExpired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at === null || $token->expires_at->isPast()) {
            return true;
        }

        $idle = max(1, (int) config('admin.session.idle_minutes', 60));
        $lastSeen = $token->last_used_at ?? $token->created_at;

        return $lastSeen === null || $lastSeen->lte(now()->subMinutes($idle));
    }
}
