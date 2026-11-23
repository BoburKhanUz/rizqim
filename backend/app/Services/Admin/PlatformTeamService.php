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
 * demotes themselves, the last superadmin who can sign in stays, and only a superadmin touches a superadmin (or makes
 * one), even when the superadmin gave team.manage to another role. Review F1: likewise only a superadmin gives a role
 * carrying a privileged permission (AdminPermissionService::PRIVILEGED: team.manage, pii.view, system.manage, refund
 * and payout approval) or changes a member holding one, so a team.manage holder cannot build a second account with
 * more rights. Disabling or resetting 2FA ends every session.
 *
 * Wave P: a member holds a set of roles (User::platformRoles, at least one). Every guard reads the whole set: a set
 * holding superadmin or any privileged role is a superadmin's to give, a member with one such role among others is a
 * superadmin's to change, nobody changes their own set, and the last superadmin anybody can sign in as keeps
 * superadmin in theirs.
 */
class PlatformTeamService
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly TwoFactorService $twoFactor,
        private readonly TokenService $tokens,
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * A new member, disabled until they accept the invite (no password yet), and the one-time invite link.
     *
     * @param  list<PlatformRole>|PlatformRole  $roles  at least one
     * @return array{member: User, token: string, expires_at: Carbon}
     *
     * @throws AdminAuthException
     * @throws \InvalidArgumentException for an empty set
     */
    public function invite(string $name, string $email, array|PlatformRole $roles, ?User $actor): array
    {
        $roles = self::roleSet($roles);
        $this->assertMayGrant($actor, $roles);
        $email = mb_strtolower(trim($email));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new AdminAuthException("Bu email bilan hisob allaqachon bor.", 'email_taken', 422);
        }

        return DB::transaction(function () use ($name, $email, $roles, $actor) {
            $member = new User();
            $member->forceFill([
                'name' => trim($name),
                'email' => $email,
                'password' => null,
                // The model's saving hook also sets platform_role (the primary role) and users.role from the set
                'platform_roles' => self::values($roles),
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
        if ($member->platformRoles() !== []) {
            // Whoever holds the link becomes this member: the same rule as inviting into their roles
            $this->assertMayGrant($actor, $member->platformRoles());
        }

        if (!self::isInvited($member)) {
            throw new AdminAuthException("A'zo taklifni allaqachon qabul qilgan.", 'invite_already_accepted', 409);
        }

        return $this->issueInvite($member, $actor);
    }

    /**
     * `rizqim:admin:invite`: a new invite link for a member who already has an account (an old super_admin without 2FA,
     * a forgotten password). Accepting it sets a new password, ends the member's sessions and, without 2FA, opens the
     * set-up window. Server shell only: there is no API for it.
     *
     * @return array{token: string, expires_at: Carbon}
     *
     * @throws AdminAuthException
     */
    public function inviteExisting(User $member): array
    {
        if (!$member->isPlatformStaff()) {
            throw new AdminAuthException("Bu hisob jamoa a'zosi emas.", 'not_found', 404);
        }
        if ($member->platform_disabled_at !== null) {
            throw new AdminAuthException("A'zo o'chirilgan: avval uni Tizim → Jamoa'da yoqing.", 'member_disabled', 409);
        }

        return $this->issueInvite($member, null);
    }

    /**
     * POST /admin/auth/invite/accept: the invite is spent and the password set (at least 12 characters, not the
     * email); the member goes on to the 2FA set-up (its window opens for 24 hours, review F2). A member who already
     * had a password (re-invited from the command line) loses every session. A refused password leaves the invite
     * usable.
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

            $hadPassword = filled($member->password);
            $invite->forceFill(['accepted_at' => now()])->save();
            $member->forceFill(['password' => $password])->save();

            if (!$member->hasTwoFactorEnabled()) {
                $this->twoFactor->openSetupWindow($member);
            }
            if ($hadPassword) {
                $this->endSessions($member);
            }

            return $member;
        });
    }

    /**
     * PATCH /admin/team/{id}: the member's set of roles (replaced as a whole) and/or enabled state.
     *
     * @param  list<PlatformRole>|PlatformRole|null  $roles  null: unchanged; else the new set, at least one role
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws AdminAuthException
     * @throws \InvalidArgumentException for an empty set
     */
    public function update(User $member, array|PlatformRole|null $roles, ?bool $disabled, User $actor): array
    {
        $roles = $roles === null ? null : self::roleSet($roles);
        // Early answer on the copy the request loaded; checked again below on the locked row
        $this->assertMayUpdate($member, $roles, $disabled, $actor);

        $before = DB::transaction(function () use ($member, $roles, $disabled, $actor) {
            // Review 16: the member's row is locked and the rules read again from it, so a change made meanwhile by
            // another request (a privileged role given, superadmin added) is what they judge, not the stale copy
            $locked = User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->assertMayUpdate($locked, $roles, $disabled, $actor);
            $before = self::state($locked);

            $leavesSuperadmins = $locked->isPlatformSuperadmin()
                && (($roles !== null && !in_array(PlatformRole::SUPERADMIN, $roles, true)) || $disabled === true);

            if ($leavesSuperadmins) {
                // Serialised: two superadmins demoting each other at the same moment cannot leave none. Review F7: only
                // one somebody can sign in as counts (active, a password, 2FA on): not an invite never accepted, nor
                // an old super_admin who never set 2FA up
                // Wave P: whoever holds superadmin among their roles
                $others = User::query()
                    ->whereJsonContains('platform_roles', PlatformRole::SUPERADMIN->value)
                    ->lockForUpdate()
                    ->get()
                    ->filter(fn (User $superadmin) => (int) $superadmin->id !== (int) $locked->id && self::canSignIn($superadmin))
                    ->count();

                if ($others === 0) {
                    throw new AdminAuthException("Oxirgi superadminni o'chirib yoki undan superadmin rolini olib bo'lmaydi.", 'last_superadmin', 422);
                }
            }

            if ($roles !== null) {
                $locked->platform_roles = self::values($roles);
            }
            if ($disabled !== null) {
                $locked->platform_disabled_at = $disabled ? ($locked->platform_disabled_at ?? now()) : null;
            }
            $locked->save();

            if ($disabled === true) {
                $this->endSessions($locked);
            }

            return $before;
        });

        return ['before' => $before, 'after' => self::state($member->refresh())];
    }

    /**
     * The rules of a change to a member: only a superadmin touches a superadmin or privileged member or gives such a
     * set, and nobody changes their own roles or disables themselves.
     *
     * @param  list<PlatformRole>|null  $roles
     *
     * @throws AdminAuthException
     */
    private function assertMayUpdate(User $member, ?array $roles, ?bool $disabled, User $actor): void
    {
        $this->assertMayManage($actor, $member);
        if ($roles !== null) {
            $this->assertMayGrant($actor, $roles);
        }

        if ((int) $member->id === (int) $actor->id && (($roles !== null && $roles !== $member->platformRoles()) || $disabled === true)) {
            throw new AdminAuthException("O'zingizning rollaringizni o'zgartira yoki hisobingizni o'chira olmaysiz.", 'cannot_change_self', 422);
        }
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

    /**
     * Somebody can sign in as this member now: active, with a password and 2FA on.
     */
    public static function canSignIn(User $member): bool
    {
        return $member->isActivePlatformStaff() && filled($member->password) && $member->hasTwoFactorEnabled();
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
     * Wave P: what the audit log records of a member: the whole set of roles (before / after) and the enabled state.
     *
     * @return array{platform_roles: list<string>, disabled: bool}
     */
    private static function state(User $member): array
    {
        return ['platform_roles' => self::values($member->platformRoles()), 'disabled' => $member->platform_disabled_at !== null];
    }

    /**
     * @param  list<PlatformRole>|PlatformRole  $roles
     * @return non-empty-list<PlatformRole>
     *
     * @throws \InvalidArgumentException for an empty set
     */
    private static function roleSet(array|PlatformRole $roles): array
    {
        $set = User::normalizePlatformRoles($roles instanceof PlatformRole ? [$roles] : $roles);
        if ($set === []) {
            throw new \InvalidArgumentException('A team member holds at least one platform role.');
        }

        return $set;
    }

    /**
     * @param  list<PlatformRole>  $roles
     * @return list<string>
     */
    private static function values(array $roles): array
    {
        return array_map(fn (PlatformRole $role) => $role->value, $roles);
    }

    /**
     * Wave P: the whole set is checked: superadmin, or any role holding a privileged permission, next to harmless
     * roles is still a superadmin's to give.
     *
     * @param  list<PlatformRole>  $roles
     *
     * @throws AdminAuthException
     */
    private function assertMayGrant(?User $actor, array $roles): void
    {
        if ($actor === null || $actor->isPlatformSuperadmin()) {
            return;
        }
        if (in_array(PlatformRole::SUPERADMIN, $roles, true)) {
            throw new AdminAuthException("Superadmin rolini faqat superadmin bera oladi.", 'superadmin_only', 403);
        }
        if ($this->permissions->privilegedOfRoles($roles) !== []) {
            throw new AdminAuthException(
                "Tanlangan rollardan birida jamoa, shaxsiy ma'lumot, tizim yoki pulni tasdiqlash ruxsati bor: uni faqat superadmin bera oladi.",
                'superadmin_only',
                403,
            );
        }
    }

    /**
     * @throws AdminAuthException
     */
    private function assertMayManage(?User $actor, User $member): void
    {
        if ($actor === null || $actor->isPlatformSuperadmin()) {
            return;
        }
        if ($member->isPlatformSuperadmin()) {
            throw new AdminAuthException("Superadmin hisobini faqat superadmin o'zgartira oladi.", 'superadmin_only', 403);
        }
        if ($this->permissions->privilegedOfRoles($member->platformRoles()) !== []) {
            throw new AdminAuthException(
                "Bu a'zoning rollaridan birida jamoa, shaxsiy ma'lumot, tizim yoki pulni tasdiqlash ruxsati bor: uni faqat superadmin o'zgartira oladi.",
                'superadmin_only',
                403,
            );
        }
    }
}
