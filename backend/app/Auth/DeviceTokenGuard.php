<?php

namespace App\Auth;

use App\Domain\Pos\Releases\AppVersion;
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
    // Wave M1: the token's last_used_at at most once per SANCTUM_LAST_USED_AT_THROTTLE_MINUTES (the device heartbeat
    // below stays once a minute)
    use ThrottlesLastUsedAt;

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

        // A heartbeat for the owner's device list, at most once a minute. Wave M5 S5-a: the app version the terminal
        // reports (X-Rizqim-App-Version) rides on this same save, and only when it changed: no extra write. A change
        // is therefore seen within a minute. Nothing reads it to refuse a request (a sale is never gated by version)
        if (!$device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()]);
            $reported = trim((string) $request->header(AppVersion::HEADER, ''));
            if ($reported !== '' && $reported !== $device->app_version && AppVersion::parse($reported) !== null) {
                $device->forceFill(['app_version' => $reported]);
            }
            $device->saveQuietly();
            $this->updateLastUsedAt($accessToken);
        }

        $request->attributes->set('device', $device);

        return $device;
    }
}
