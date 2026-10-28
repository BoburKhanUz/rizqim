<?php

namespace App\Services\Admin;

use App\Auth\AdminTokenGuard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * P4-11: admin sessions, one Sanctum token row per sign-in (AdminTokenGuard reads them): opened after the second
 * factor, listed in Sessiyalarim, revoked one by one, all at once (a disabled member, a 2FA reset) or by logging out.
 * A step-up confirmation (POST /admin/auth/confirm) marks the session itself for 10 minutes.
 */
class AdminSessionService
{
    public function open(User $user, Request $request): NewAccessToken
    {
        $expiresAt = now()->addMinutes(max(1, (int) config('admin.session.lifetime_minutes', 720)));
        $token = $user->createToken(AdminTokenGuard::NAME, [AdminTokenGuard::ABILITY], $expiresAt);

        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'last_used_at' => now(),
        ])->save();

        $user->forceFill(['platform_last_login_at' => now()])->saveQuietly();

        return $token;
    }

    /**
     * @return Collection<int, PersonalAccessToken> the member's live admin sessions, newest first
     */
    public function sessionsOf(User $user): Collection
    {
        return $this->query($user)
            ->orderByDesc('id')
            ->get()
            ->reject(fn (PersonalAccessToken $token) => AdminTokenGuard::hasExpired($token))
            ->values();
    }

    public function activeCount(User $user): int
    {
        return $this->sessionsOf($user)->count();
    }

    public function find(User $user, int $id): ?PersonalAccessToken
    {
        $token = $this->query($user)->whereKey($id)->first();

        return $token !== null && !AdminTokenGuard::hasExpired($token) ? $token : null;
    }

    public function revokeAll(User $user): int
    {
        return $this->query($user)->delete();
    }

    /**
     * The admin session of this request (the `admin` guard always sets a token row; null for any other sign-in).
     */
    public function current(User $user): ?PersonalAccessToken
    {
        /** @var mixed $token a TransientToken for a session sign-in (tests acting as a user) */
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken && AdminTokenGuard::isAdminToken($token) ? $token : null;
    }

    public function markReauthenticated(PersonalAccessToken $token): Carbon
    {
        $token->forceFill(['reauthenticated_at' => now()])->save();

        return $this->reauthenticatedUntil($token) ?? now();
    }

    public function reauthenticatedUntil(PersonalAccessToken $token): ?Carbon
    {
        $at = $token->getAttribute('reauthenticated_at');
        if ($at === null) {
            return null;
        }

        $until = Carbon::parse($at)->addMinutes(max(1, (int) config('admin.session.reauth_minutes', 10)));

        return $until->isFuture() ? $until : null;
    }

    /**
     * @return array{id: int, ip: string|null, user_agent: string|null, created_at: string|null, last_used_at: string|null, expires_at: string|null, reauthenticated_until: string|null, current: bool}
     */
    public function present(PersonalAccessToken $token, ?PersonalAccessToken $current = null): array
    {
        return [
            'id' => (int) $token->getKey(),
            'ip' => $token->getAttribute('ip_address'),
            'user_agent' => $token->getAttribute('user_agent'),
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'reauthenticated_until' => $this->reauthenticatedUntil($token)?->toIso8601String(),
            'current' => $current !== null && (int) $current->getKey() === (int) $token->getKey(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<PersonalAccessToken>
     */
    private function query(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('name', AdminTokenGuard::NAME);
    }
}
