<?php

namespace App\Auth;

use App\Models\Device;
use Illuminate\Http\Request;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\Guard;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * The `device` guard: a paired POS/KDS terminal authenticates with its own Sanctum token (tokenable = Device,
 * ability "terminal"). Unlike Sanctum's own guard it never falls back to a web session user, and the global
 * SANCTUM_TOKEN_EXPIRATION does not apply: a terminal token lives until its own expires_at (AUTH_DEVICE_TOKEN_TTL_DAYS)
 * or until the device is revoked. The `sanctum` guard (provider: users) never accepts these tokens.
 */
class DeviceTokenGuard extends Guard
{
    public const ABILITY = 'terminal';

    public function __invoke(Request $request)
    {
        $token = $this->getTokenFromRequest($request);
        if (!$token) {
            return null;
        }

        $model = Sanctum::$personalAccessTokenModel;
        $accessToken = $model::findToken($token);

        if (!$accessToken instanceof PersonalAccessToken
            || !$this->isValidAccessToken($accessToken)
            || !$accessToken->can(self::ABILITY)) {
            return null;
        }

        $device = $accessToken->tokenable;
        if (!$device instanceof Device || $device->isRevoked() || (int) $accessToken->getAttribute('device_id') !== (int) $device->id) {
            return null;
        }

        $device->withAccessToken($accessToken);
        event(new TokenAuthenticated($accessToken));

        // A heartbeat for the owner's device list, at most once a minute
        if (!$device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
            $this->updateLastUsedAt($accessToken);
        }

        $request->attributes->set('device', $device);

        return $device;
    }
}
