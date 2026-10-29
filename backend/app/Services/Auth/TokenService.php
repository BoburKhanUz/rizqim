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

            $refresh = RefreshToken::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'family_id' => $familyId ?? (string) Str::uuid(),
                'token_hash' => self::hash($refreshPlain),
                'access_token_id' => $access->accessToken->getKey(),
                'expires_at' => $refreshExpiresAt,
            ]);

            return new IssuedTokens($access->plainTextToken, $accessExpiresAt, $refreshPlain, $refreshExpiresAt, $device, (int) $refresh->getKey());
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

            $device = $current->device;
            // The answer carries the device's signed-in user (Device::toPublicArray shows it only when loaded): a web
            // client restoring its session from the refresh cookie learns who is signed in from it
            if ($device && (int) $device->user_id === (int) $current->user_id && $current->user) {
                $device->setRelation('user', $current->user);
            }
            $rightDevice = $device && !$device->isRevoked() && hash_equals($device->uuid, $deviceUuid)
                && (int) $device->user_id === (int) $current->user_id;

            // The answer to a refresh got lost on the way (network drop) and the app retries with the token it just
            // spent: rotate once more, ending the pair the app never received, instead of logging it out
            if ($current->used_at !== null && $current->revoked_at === null && $device && $rightDevice && $current->user
                && !$current->user->isPlatformAccount()
                && ($successor = $this->unclaimedSuccessor($current)) !== null) {
                $this->revokeToken($successor);
                $current->forceFill(['grace_used_at' => now()])->save();
                $next = $this->issuePair($current->user, $device, $current->family_id);
                $current->forceFill(['replaced_by_id' => $next->refreshTokenId])->save();

                return $next;
            }

            // Spent before: somebody else holds a copy of it. A logged-out (revoked) one is simply dead
            if ($current->used_at !== null || $current->revoked_at !== null) {
                $this->revokeFamily($current->family_id);

                return $current->used_at !== null ? 'reused' : 'invalid';
            }

            if (!$device || !$rightDevice) {
                $this->revokeFamily($current->family_id);

                return 'invalid';
            }

            if ($current->expires_at->isPast() || !$current->user) {
                $current->forceFill(['revoked_at' => now()])->save();

                return 'expired';
            }

            // P4-25: a superadmin / platform account holds no app session; a refresh token it got earlier mints nothing
            if ($current->user->isPlatformAccount()) {
                $this->revokeFamily($current->family_id);

                return 'invalid';
            }

            $current->forceFill(['used_at' => now()])->save();
            if ($current->access_token_id) {
                PersonalAccessToken::whereKey($current->access_token_id)->delete();
            }

            $device->forceFill(['last_seen_at' => now()])->save();

            $next = $this->issuePair($current->user, $device, $current->family_id);
            $current->forceFill(['replaced_by_id' => $next->refreshTokenId])->save();

            return $next;
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
     * The pair issued when $spent was spent, if the app has evidently not received it: $spent was spent within the
     * grace window (auth.tokens.refresh_grace_seconds), no retry has used the grace yet, and its successor was neither
     * used nor revoked since. Anything else presenting a spent token is a replay.
     */
    private function unclaimedSuccessor(RefreshToken $spent): ?RefreshToken
    {
        $grace = min(60, (int) config('auth.tokens.refresh_grace_seconds', 30));

        if ($grace <= 0 || $spent->used_at === null || $spent->grace_used_at !== null || $spent->replaced_by_id === null
            || $spent->used_at->lt(now()->subSeconds($grace))) {
            return null;
        }

        return RefreshToken::whereKey($spent->replaced_by_id)
            ->where('family_id', $spent->family_id)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Revoke one refresh token and the access token issued with it.
     */
    private function revokeToken(RefreshToken $token): void
    {
        $token->forceFill(['revoked_at' => now()])->save();
        if ($token->access_token_id) {
            PersonalAccessToken::whereKey($token->access_token_id)->delete();
        }
    }

    /**
     * Log one device out: its refresh tokens and every token of the user bound to it.
     */
    public function revokeUserDevice(User $user, Device $device): void
    {
        RefreshToken::where('user_id', $user->id)->where('device_id', $device->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        $this->deviceTokens($user, $device)->delete();
        // Signed out: this install must not keep receiving the person's pushes (P1-22)
        $device->forceFill(['push_token' => null])->save();
    }

    /**
     * Log a user out everywhere: every token (Mini App, apps, staff PIN sessions) and refresh token.
     */
    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $user->tokens()->delete();
        Device::where('user_id', $user->id)->whereNotNull('push_token')->update(['push_token' => null]);
    }

    /**
     * End the user's app sessions on every device (refresh token families and their access tokens), e.g. when their
     * staff login phone changes or is cleared (P1-34). The Mini App token (Telegram) and terminal PIN sessions
     * (bound to a paired terminal and the PIN, not to the phone) stay.
     */
    public function revokeAppSessions(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $user->tokens()->where('name', self::ACCESS_TOKEN_NAME)->delete();
        // The apps signed out must not keep receiving the person's pushes (P1-22)
        Device::where('user_id', $user->id)->whereNotNull('push_token')->update(['push_token' => null]);
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
        // P4-31: a blocked account gets no app session, refresh or staff PIN session (403 account_blocked)
        AccountBlockedException::guard($tokenable);

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
