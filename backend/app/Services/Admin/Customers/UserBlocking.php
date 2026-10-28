<?php

namespace App\Services\Admin\Customers;

use App\Auth\AdminTokenGuard;
use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * P4-31: blocking an account and closing its sessions from Mijozlar va yordam (admin.support.manage, a step-up and a
 * reason each, audited by the controller).
 *
 * - block: users.blocked_at / blocked_by / blocked_reason, and every session ends. A blocked account signs in nowhere
 *   (Telegram, SMS code, refresh, staff PIN: AccountBlockedException), no token issued before works (the Sanctum
 *   check in AccessServiceProvider), so it cannot order; a blocked staff member's Telegram order buttons stop too.
 *   A blocked owner's store keeps working (suspending a store is Ovqatxonalar' action).
 * - unblock: clears the three columns; the person signs in again.
 * - closeSessions: every token and refresh token of the account (Mini App, apps, staff PIN sessions on terminals),
 *   and its devices stop getting pushes. A terminal's own token belongs to the device, not to the person, and stays.
 *
 * An admin session token is never touched here, and a RIZQIM team account is never acted on (Jamoa disables those).
 * The old sanctum:prune-expired is never run (it would delete paired terminals' tokens too).
 */
class UserBlocking
{
    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>, sessions: array{tokens: int, refresh_tokens: int}}
     *
     * @throws CustomerAdminException
     */
    public function block(User $user, User $member, string $reason): array
    {
        $this->assertActionable($user);
        if ($user->isBlocked()) {
            throw new CustomerAdminException('Hisob allaqachon bloklangan.', 'already_blocked', 409);
        }

        return DB::transaction(function () use ($user, $member, $reason) {
            $user->forceFill([
                'blocked_at' => now(),
                'blocked_by' => $member->id,
                'blocked_reason' => mb_substr(trim($reason), 0, 500),
            ])->save();

            $sessions = $this->revokeSessions($user);

            return [
                'before' => ['blocked' => false],
                'after' => ['blocked' => true],
                'sessions' => $sessions,
            ];
        });
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws CustomerAdminException
     */
    public function unblock(User $user): array
    {
        $this->assertActionable($user);
        if (!$user->isBlocked()) {
            throw new CustomerAdminException('Hisob bloklanmagan.', 'not_blocked', 409);
        }

        $user->forceFill(['blocked_at' => null, 'blocked_by' => null, 'blocked_reason' => null])->save();

        return ['before' => ['blocked' => true], 'after' => ['blocked' => false]];
    }

    /**
     * @return array{tokens: int, refresh_tokens: int}
     *
     * @throws CustomerAdminException
     */
    public function closeSessions(User $user): array
    {
        $this->assertActionable($user);

        return DB::transaction(fn () => $this->revokeSessions($user));
    }

    /**
     * @throws CustomerAdminException
     */
    public function assertActionable(User $user): void
    {
        if ($user->isPlatformAccount()) {
            throw new CustomerAdminException(
                "RIZQIM jamoasi a'zosi bu yerda boshqarilmaydi: Tizim → Jamoa bo'limidan foydalaning.",
                'platform_account',
                403,
            );
        }
    }

    /**
     * @return array{tokens: int, refresh_tokens: int}
     */
    private function revokeSessions(User $user): array
    {
        $refresh = RefreshToken::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            // Never an admin session (a team account is refused above; this is the second lock)
            ->where('name', '!=', AdminTokenGuard::NAME)
            ->delete();

        // Signed out everywhere: no more pushes to its installs (P1-22)
        Device::query()->where('user_id', $user->id)->whereNotNull('push_token')->update(['push_token' => null]);

        return ['tokens' => (int) $tokens, 'refresh_tokens' => (int) $refresh];
    }
}
