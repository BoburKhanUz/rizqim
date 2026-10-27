<?php

namespace App\Services\Admin;

use App\Enums\PlatformRole;
use App\Models\PlatformInvite;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * P4-11: the RIZQIM team (Tizim → Jamoa): invites, role changes, disabling, 2FA resets. Guards: nobody disables or
 * demotes themselves, the last active superadmin stays, and only a superadmin touches a superadmin (or makes one),
 * even when the superadmin gave team.manage to another role. Disabling or resetting 2FA ends every session.
 */
class PlatformTeamService
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly TwoFactorService $twoFactor,
        private readonly TokenService $tokens,
    ) {}

    /**
     * A new member, disabled until they accept the invite (no password yet), and the one-time invite link.
     *
     * @return array{member: User, token: string, expires_at: Carbon}
     *
     * @throws AdminAuthException
     */
    public function invite(string $name, string $email, PlatformRole $role, ?User $actor): array
    {
        $this->assertMayGrant($actor, $role);
        $email = mb_strtolower(trim($email));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new AdminAuthException("Bu email bilan hisob allaqachon bor.", 'email_taken', 422);
        }

        return DB::transaction(function () use ($name, $email, $role, $actor) {
            $member = new User();
            $member->forceFill([
                'name' => trim($name),
                'email' => $email,
                'password' => null,
                'role' => $role->userRole(),
                'platform_role' => $role,
            ])->save();

            return ['member' => $member, ...$this->issueInvite($member, $actor)];
        });
    }

    /**
     * A new invite link (older ones stop working). Only for a member who has not accepted yet.
     *
     * @return array{token: string, expires_at: Carbon}
     *
     * @throws AdminAuthException
     */
    public function reissueInvite(User $member, ?User $actor): array
    {
        $this->assertMayManage($actor, $member);

        if (!self::isInvited($member)) {
            throw new AdminAuthException("A'zo taklifni allaqachon qabul qilgan.", 'invite_already_accepted', 409);
        }

        return $this->issueInvite($member, $actor);
    }

    /**
     * POST /admin/auth/invite/accept: the invite is spent and the password set (at least 12 characters, not the
     * email); the member goes on to the 2FA set-up. A refused password leaves the invite usable.
     *
     * @throws AdminAuthException
     * @throws ValidationException
     */
    public function acceptInvite(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($token, $password) {
            $invite = PlatformInvite::query()->where('token_hash', AdminAuthService::hash($token))->lockForUpdate()->first();
            $member = $invite?->user;

            if ($invite === null || $member === null || !$invite->isUsable() || !$member->isPlatformStaff()) {
                throw new AdminAuthException("Taklif havolasi eskirgan yoki ishlatilgan. Yangi havola so'rang.", 'invite_invalid', 422);
            }

            $email = mb_strtolower((string) $member->email);
            $plain = mb_strtolower($password);
            if ($plain === $email || ($email !== '' && $plain === strstr($email, '@', true))) {
                throw ValidationException::withMessages(['password' => "Parol email bilan bir xil bo'lmasligi kerak."]);
            }

            $invite->forceFill(['accepted_at' => now()])->save();
            $member->forceFill(['password' => $password])->save();

            return $member;
        });
    }

    /**
     * PATCH /admin/team/{id}: role and/or enabled state.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws AdminAuthException
     */
    public function update(User $member, ?PlatformRole $role, ?bool $disabled, User $actor): array
    {
        $this->assertMayManage($actor, $member);
        if ($role !== null) {
            $this->assertMayGrant($actor, $role);
        }

        $before = self::state($member);
        $self = (int) $member->id === (int) $actor->id;

        if ($self && (($role !== null && $role !== $member->platform_role) || $disabled === true)) {
            throw new AdminAuthException("O'zingizning rolingizni o'zgartira yoki hisobingizni o'chira olmaysiz.", 'cannot_change_self', 422);
        }

        $leavesSuperadmins = $member->isPlatformSuperadmin()
            && (($role !== null && $role !== PlatformRole::SUPERADMIN) || $disabled === true);

        DB::transaction(function () use ($member, $role, $disabled, $leavesSuperadmins) {
            if ($leavesSuperadmins) {
                // Serialised: two superadmins demoting each other at the same moment cannot leave none
                $others = User::query()
                    ->where('platform_role', PlatformRole::SUPERADMIN->value)
                    ->lockForUpdate()
                    ->get(['id', 'platform_disabled_at'])
                    ->filter(fn (User $superadmin) => $superadmin->platform_disabled_at === null && (int) $superadmin->id !== (int) $member->id)
                    ->count();

                if ($others === 0) {
                    throw new AdminAuthException("Oxirgi superadminni o'chirib yoki rolini o'zgartirib bo'lmaydi.", 'last_superadmin', 422);
                }
            }

            if ($role !== null) {
                $member->platform_role = $role;
            }
            if ($disabled !== null) {
                $member->platform_disabled_at = $disabled ? ($member->platform_disabled_at ?? now()) : null;
            }
            $member->save();

            if ($disabled === true) {
                $this->endSessions($member);
            }
        });

        return ['before' => $before, 'after' => self::state($member->refresh())];
    }

    /**
     * POST /admin/team/{id}/reset-2fa (and `rizqim:admin:reset-2fa`): 2FA off, recovery codes gone, every session
     * ended; the member sets 2FA up again at their next sign-in.
     *
     * @throws AdminAuthException
     */
    public function resetTwoFactor(User $member, ?User $actor): void
    {
        if ($actor !== null) {
            $this->assertMayManage($actor, $member);
            if ((int) $member->id === (int) $actor->id) {
                throw new AdminAuthException("O'zingizning 2FA'ingizni bu yerdan o'chira olmaysiz.", 'cannot_change_self', 422);
            }
        }

        DB::transaction(function () use ($member) {
            $this->twoFactor->disable($member);
            $this->endSessions($member);
        });
    }

    /**
     * Every admin session and, for a superadmin, their store-level tokens and app sessions too.
     */
    public function endSessions(User $member): void
    {
        $this->sessions->revokeAll($member);
        $this->tokens->revokeAllForUser($member);
    }

    public static function isInvited(User $member): bool
    {
        return $member->isPlatformStaff() && blank($member->password);
    }

    /**
     * invited (no password yet), disabled or active.
     */
    public static function status(User $member): string
    {
        return match (true) {
            $member->platform_disabled_at !== null => 'disabled',
            self::isInvited($member) => 'invited',
            default => 'active',
        };
    }

    public function pendingInvite(User $member): ?PlatformInvite
    {
        return PlatformInvite::query()
            ->where('user_id', $member->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public static function inviteUrl(string $token): string
    {
        return config('admin.app_url') . '/invite/' . $token;
    }

    /**
     * @return array{token: string, expires_at: Carbon}
     */
    private function issueInvite(User $member, ?User $actor): array
    {
        PlatformInvite::query()->where('user_id', $member->id)->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        $token = 'pi_' . Str::random(48);
        $expiresAt = now()->addHours(max(1, (int) config('admin.invite.ttl_hours', 72)));

        PlatformInvite::create([
            'user_id' => $member->id,
            'token_hash' => AdminAuthService::hash($token),
            'expires_at' => $expiresAt,
            'created_by' => $actor?->id,
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * @return array{platform_role: string|null, disabled: bool}
     */
    private static function state(User $member): array
    {
        return ['platform_role' => $member->platform_role?->value, 'disabled' => $member->platform_disabled_at !== null];
    }

    /**
     * @throws AdminAuthException
     */
    private function assertMayGrant(?User $actor, PlatformRole $role): void
    {
        if ($actor !== null && $role === PlatformRole::SUPERADMIN && !$actor->isPlatformSuperadmin()) {
            throw new AdminAuthException("Superadmin rolini faqat superadmin bera oladi.", 'superadmin_only', 403);
        }
    }

    /**
     * @throws AdminAuthException
     */
    private function assertMayManage(?User $actor, User $member): void
    {
        if ($actor !== null && $member->isPlatformSuperadmin() && !$actor->isPlatformSuperadmin()) {
            throw new AdminAuthException("Superadmin hisobini faqat superadmin o'zgartira oladi.", 'superadmin_only', 403);
        }
    }
}
