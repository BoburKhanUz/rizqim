<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Device-bound sessions of the native apps: a short-lived Sanctum access token plus a rotating refresh token.
 *
 * Every refresh spends the presented refresh token and issues the next one in the same family. Presenting a spent
 * (or revoked) token again, or a token from another device, means it leaked: the whole family and its access tokens
 * are revoked, so both the thief and the real app have to sign in again. Only SHA-256 hashes are stored.
 *
 * The Telegram Mini App does not use this (it re-authenticates with initData on a 401).
 */
class TokenService
{
    public const ACCESS_TOKEN_NAME = 'device-access';
    public const STAFF_SESSION_NAME = 'staff-session';
    // The only ability of a staff PIN session token: routes behind the `not-staff-session` middleware refuse it
    public const STAFF_SESSION_ABILITY = 'staff-session';
    public const TERMINAL_TOKEN_NAME = 'terminal';

    public function issuePair(User $user, Device $device, ?string $familyId = null): IssuedTokens
    {
        return DB::transaction(function () use ($user, $device, $familyId) {
            // One live access token per user and device
            $this->deviceTokens($user, $device)->where('name', self::ACCESS_TOKEN_NAME)->delete();

            // A new sign-in on the device ends its older sessions: their refresh tokens stop working too
            if ($familyId === null) {
                RefreshToken::where('user_id', $user->id)->where('device_id', $device->id)->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            $accessExpiresAt = now()->addMinutes(max(1, (int) config('auth.tokens.access_ttl', 15)));
            $access = $this->createDeviceToken($user, $device, self::ACCESS_TOKEN_NAME, ['*'], $accessExpiresAt);

            $refreshPlain = 'rt_' . Str::random(64);
            $refreshExpiresAt = now()->addDays(max(1, (int) config('auth.tokens.refresh_ttl_days', 30)));

            RefreshToken::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'family_id' => $familyId ?? (string) Str::uuid(),
                'token_hash' => self::hash($refreshPlain),
                'access_token_id' => $access->accessToken->getKey(),
                'expires_at' => $refreshExpiresAt,
            ]);

            return new IssuedTokens($access->plainTextToken, $accessExpiresAt, $refreshPlain, $refreshExpiresAt, $device);
        });
    }

    /**
     * Spend a refresh token and issue the next pair.
     *
     * @throws AuthTokenException
     */
    public function refresh(string $refreshToken, string $deviceUuid): IssuedTokens
    {
        // The rejection is thrown after the transaction commits, so a family revoked for reuse stays revoked
        $result = DB::transaction(function () use ($refreshToken, $deviceUuid) {
            $current = RefreshToken::where('token_hash', self::hash($refreshToken))->lockForUpdate()->first();

            if (!$current) {
                return 'invalid';
            }

            // Spent before: somebody else holds a copy of it. A logged-out (revoked) one is simply dead
            if ($current->used_at !== null || $current->revoked_at !== null) {
                $this->revokeFamily($current->family_id);

                return $current->used_at !== null ? 'reused' : 'invalid';
            }

            $device = $current->device;
            if (!$device || $device->isRevoked() || !hash_equals($device->uuid, $deviceUuid) || (int) $device->user_id !== (int) $current->user_id) {
                $this->revokeFamily($current->family_id);

                return 'invalid';
            }

            if ($current->expires_at->isPast() || !$current->user) {
                $current->forceFill(['revoked_at' => now()])->save();

                return 'expired';
            }

            $current->forceFill(['used_at' => now()])->save();
            if ($current->access_token_id) {
                PersonalAccessToken::whereKey($current->access_token_id)->delete();
            }

            $device->forceFill(['last_seen_at' => now()])->save();

            return $this->issuePair($current->user, $device, $current->family_id);
        });

        if ($result instanceof IssuedTokens) {
            return $result;
        }

        throw new AuthTokenException(
            $result === 'reused'
                ? "Sessiya xavfsizlik sababli yopildi. Iltimos, qaytadan kiring."
                : "Sessiya muddati tugagan yoki yaroqsiz. Iltimos, qaytadan kiring.",
            "refresh_{$result}",
        );
    }

    /**
     * Log one device out: its refresh tokens and every token of the user bound to it.
     */
    public function revokeUserDevice(User $user, Device $device): void
    {
        RefreshToken::where('user_id', $user->id)->where('device_id', $device->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        $this->deviceTokens($user, $device)->delete();
    }

    /**
     * Log a user out everywhere: every token (Mini App, apps, staff PIN sessions) and refresh token.
     */
    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $user->tokens()->delete();
    }

    /**
     * End everything bound to a device: its own terminal token, staff sessions on it, refreshable app sessions.
     */
    public function revokeDevice(Device $device): void
    {
        RefreshToken::where('device_id', $device->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        PersonalAccessToken::where('device_id', $device->id)->delete();
    }

    public function revokeFamily(string $familyId): void
    {
        $members = RefreshToken::where('family_id', $familyId)->get(['id', 'access_token_id']);

        RefreshToken::where('family_id', $familyId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        PersonalAccessToken::whereIn('id', $members->pluck('access_token_id')->filter()->all())->delete();
    }

    /**
     * A Sanctum token bound to a device (personal_access_tokens.device_id).
     *
     * @param  User|Device  $tokenable
     * @param  list<string>  $abilities
     */
    public function createDeviceToken($tokenable, Device $device, string $name, array $abilities, ?Carbon $expiresAt): \Laravel\Sanctum\NewAccessToken
    {
        $token = $tokenable->createToken($name, $abilities, $expiresAt);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();

        return $token;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<PersonalAccessToken>
     */
    private function deviceTokens(User $user, Device $device): \Illuminate\Database\Eloquent\Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->where('device_id', $device->id);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
