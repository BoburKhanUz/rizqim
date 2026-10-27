<?php

namespace App\Services\Admin;

use App\Models\AdminLoginChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * P4-11: the admin sign-in (POST /api/v1/admin/auth/*, docs/API.md "Admin API"):
 *
 *  1. email + password -> a 5 minute, single-use challenge: `otp_required` (2FA on) or `setup_required` (not yet:
 *     an existing superadmin's first sign-in, an accepted invite);
 *  2. set-up: a new secret kept on the challenge until 3. confirms it with a code (then recovery codes, once);
 *  4. otherwise a TOTP code or a recovery code (burnt) opens the session.
 *
 * The same answer for an unknown email and a wrong password; a disabled member is told so only after the right
 * password. 5 bad passwords or codes per account + address lock it for 15 minutes (AdminLoginLockout).
 */
class AdminAuthService
{
    // A hash of a random string (made once per process): an unknown email costs the same as a wrong password
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly AdminLoginLockout $lockout,
        private readonly TwoFactorService $twoFactor,
        private readonly AdminSessionService $sessions,
    ) {}

    /**
     * @throws AdminAuthException
     */
    public function login(string $email, #[SensitiveParameter] string $password, Request $request, AdminAudit $audit): AdminLoginChallenge
    {
        $account = AdminLoginLockout::accountForEmail($email);
        $audit->recordAlways()->action('admin.auth.login_failed')->actor(null)->meta(['email' => mb_strtolower(trim($email))]);

        $this->refuseWhenLocked(AdminLoginLockout::PASSWORD, $account, $request, $audit);

        $user = $this->findMember($email);
        $hash = $user?->password ?: (self::$dummyHash ??= Hash::make(Str::random(40)));
        $valid = Hash::check($password, $hash) && $user !== null && filled($user->password);

        if (!$valid) {
            $locked = $this->lockout->fail(AdminLoginLockout::PASSWORD, $account, $request->ip());
            $audit->actor($user)->subject($user)->meta(['failure' => 'invalid_credentials', 'locked' => $locked]);

            throw new AdminAuthException("Email yoki parol noto'g'ri.", 'invalid_credentials', 422);
        }

        $audit->actor($user)->subject($user);

        if ($user->platform_disabled_at !== null) {
            $audit->meta(['failure' => 'member_disabled']);

            throw new AdminAuthException("Hisobingiz o'chirilgan. Superadminga murojaat qiling.", 'member_disabled', 403);
        }

        $this->lockout->clear(AdminLoginLockout::PASSWORD, $account, $request->ip());

        $purpose = $user->hasTwoFactorEnabled() ? AdminLoginChallenge::PURPOSE_OTP : AdminLoginChallenge::PURPOSE_SETUP;
        $audit->action('admin.auth.password_accepted')->meta(['next' => $purpose]);

        return $this->issueChallenge($user, $purpose, $request);
    }

    /**
     * A challenge for a member who just set their password from an invite (the 2FA set-up follows).
     */
    public function issueChallenge(User $user, string $purpose, Request $request): AdminLoginChallenge
    {
        $plain = 'ac_' . Str::random(64);

        $challenge = AdminLoginChallenge::create([
            'user_id' => $user->id,
            'token_hash' => self::hash($plain),
            'purpose' => $purpose,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'expires_at' => now()->addMinutes(max(1, (int) config('admin.login.challenge_minutes', 5))),
        ]);
        $challenge->setAttribute('plain_token', $plain);

        return $challenge;
    }

    /**
     * POST 2fa/setup: the new secret, kept on the challenge until confirmed. Asking again with the same challenge
     * gives the same secret (a retried request), never another account's or a confirmed one.
     *
     * @return array{secret: string, otpauth_uri: string, qr_svg: string}
     *
     * @throws AdminAuthException
     */
    public function setup(#[SensitiveParameter] string $token, Request $request, AdminAudit $audit): array
    {
        $challenge = $this->challenge($token, $audit);
        $user = $challenge->user;
        $audit->action('admin.auth.2fa_setup_started')->actor($user)->subject($user);

        if ($challenge->purpose !== AdminLoginChallenge::PURPOSE_SETUP || $user->hasTwoFactorEnabled()) {
            $audit->meta(['failure' => 'two_factor_already_enabled']);

            throw new AdminAuthException("2FA allaqachon yoqilgan. Ilovadagi kodni kiriting.", 'two_factor_already_enabled', 409);
        }

        $secret = DB::transaction(function () use ($challenge) {
            /** @var AdminLoginChallenge $locked */
            $locked = AdminLoginChallenge::query()->whereKey($challenge->id)->lockForUpdate()->firstOrFail();
            if ($locked->pending_secret === null) {
                $locked->pending_secret = $this->twoFactor->generateSecret();
                $locked->save();
            }

            return (string) $locked->pending_secret;
        });

        $uri = $this->twoFactor->otpauthUri($user, $secret);

        return ['secret' => $secret, 'otpauth_uri' => $uri, 'qr_svg' => $this->twoFactor->qrSvg($uri)];
    }

    /**
     * POST 2fa/confirm: the first code turns 2FA on and opens the session; the recovery codes are returned once.
     *
     * @return array{session: \Laravel\Sanctum\NewAccessToken, user: User, recovery_codes: list<string>}
     *
     * @throws AdminAuthException
     */
    public function confirmSetup(#[SensitiveParameter] string $token, #[SensitiveParameter] string $code, Request $request, AdminAudit $audit): array
    {
        $challenge = $this->challenge($token, $audit);
        $user = $challenge->user;
        $audit->action('admin.auth.2fa_enabled')->actor($user)->subject($user);

        if ($challenge->purpose !== AdminLoginChallenge::PURPOSE_SETUP || $user->hasTwoFactorEnabled()) {
            $audit->action('admin.auth.2fa_confirm_failed')->meta(['failure' => 'two_factor_already_enabled']);

            throw new AdminAuthException("2FA allaqachon yoqilgan. Ilovadagi kodni kiriting.", 'two_factor_already_enabled', 409);
        }
        if ($challenge->pending_secret === null) {
            $audit->action('admin.auth.2fa_confirm_failed')->meta(['failure' => 'setup_not_started']);

            throw new AdminAuthException("Avval 2FA o'rnatishni boshlang.", 'setup_not_started', 409);
        }

        $this->refuseWhenLocked(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request, $audit);

        $step = $this->twoFactor->verifySecret((string) $challenge->pending_secret, $code);
        if ($step === null) {
            $this->codeFailed($user, $request, $audit, 'admin.auth.2fa_confirm_failed');
        }

        $this->spend($challenge, $audit);
        $this->lockout->clear(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request->ip());

        $codes = DB::transaction(function () use ($user, $challenge, $step) {
            $this->twoFactor->enable($user, (string) $challenge->pending_secret, (int) $step);

            return $this->twoFactor->regenerateRecoveryCodes($user);
        });

        $session = $this->sessions->open($user, $request);
        $audit->meta(['session_id' => $session->accessToken->getKey()]);

        return ['session' => $session, 'user' => $user, 'recovery_codes' => $codes];
    }

    /**
     * POST 2fa/verify: a TOTP code (replay refused) or a recovery code (burnt) opens the session.
     *
     * @throws AdminAuthException
     */
    public function verify(#[SensitiveParameter] string $token, #[SensitiveParameter] ?string $code, #[SensitiveParameter] ?string $recoveryCode, Request $request, AdminAudit $audit): \Laravel\Sanctum\NewAccessToken
    {
        $challenge = $this->challenge($token, $audit);
        $user = $challenge->user;
        $audit->action('admin.auth.login_succeeded')->actor($user)->subject($user);

        if ($challenge->purpose !== AdminLoginChallenge::PURPOSE_OTP || !$user->hasTwoFactorEnabled()) {
            $audit->action('admin.auth.2fa_failed')->meta(['failure' => 'setup_required']);

            throw new AdminAuthException("Avval 2FA ni o'rnating.", 'setup_required', 409);
        }

        $this->refuseWhenLocked(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request, $audit);

        $byRecovery = filled($recoveryCode);
        $valid = $byRecovery
            ? $this->twoFactor->useRecoveryCode($user, (string) $recoveryCode)
            : $this->twoFactor->verifyForUser($user, (string) $code);

        if (!$valid) {
            $this->codeFailed($user, $request, $audit, 'admin.auth.2fa_failed', $byRecovery ? 'invalid_recovery_code' : 'invalid_code');
        }

        $this->spend($challenge, $audit);
        $this->lockout->clear(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request->ip());

        $session = $this->sessions->open($user, $request);
        $audit->meta([
            'method' => $byRecovery ? 'recovery_code' : 'totp',
            'recovery_codes_left' => $byRecovery ? $this->twoFactor->recoveryCodesLeft($user) : null,
            'session_id' => $session->accessToken->getKey(),
        ]);

        return $session;
    }

    /**
     * POST confirm (step-up): a fresh TOTP code marks the current session confirmed for 10 minutes.
     *
     * @throws AdminAuthException
     */
    public function stepUp(User $user, #[SensitiveParameter] string $code, Request $request, AdminAudit $audit): \Illuminate\Support\Carbon
    {
        $audit->recordAlways()->action('admin.auth.reauthenticated')->subject($user);
        $this->refuseWhenLocked(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request, $audit);

        if (!$this->twoFactor->verifyForUser($user, $code)) {
            $this->codeFailed($user, $request, $audit, 'admin.auth.reauth_failed');
        }

        $this->lockout->clear(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request->ip());

        $token = $this->sessions->current($user);
        if ($token === null) {
            throw new AdminAuthException('Avtorizatsiyadan o\'tilmagan.', 'unauthenticated', 401);
        }

        return $this->sessions->markReauthenticated($token);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Team members by email, case-insensitively (soft-deleted accounts excluded).
     */
    public function findMember(string $email): ?User
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return User::query()->whereNotNull('platform_role')->whereRaw('lower(email) = ?', [$email])->first();
    }

    /**
     * @throws AdminAuthException
     */
    private function challenge(#[SensitiveParameter] string $token, AdminAudit $audit): AdminLoginChallenge
    {
        $audit->recordAlways()->action('admin.auth.challenge_invalid')->actor(null);

        $challenge = AdminLoginChallenge::query()->where('token_hash', self::hash($token))->with('user')->first();
        $user = $challenge?->user;

        if ($challenge === null || $user === null || !$challenge->isUsable() || !$user->isActivePlatformStaff()) {
            $audit->actor($user)->subject($user)->meta([
                'failure' => match (true) {
                    $challenge === null || $user === null => 'unknown',
                    $challenge->used_at !== null => 'used',
                    $challenge->expires_at->isPast() => 'expired',
                    default => 'member_disabled',
                },
            ]);

            throw new AdminAuthException("Kirish muddati tugagan. Email va parolni qaytadan kiriting.", 'challenge_invalid', 422);
        }

        return $challenge;
    }

    /**
     * Single use: a concurrent second request with the same challenge loses.
     *
     * @throws AdminAuthException
     */
    private function spend(AdminLoginChallenge $challenge, AdminAudit $audit): void
    {
        $spent = AdminLoginChallenge::query()->whereKey($challenge->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);

        if ($spent !== 1) {
            $audit->action('admin.auth.challenge_invalid')->meta(['failure' => 'used']);

            throw new AdminAuthException("Kirish muddati tugagan. Email va parolni qaytadan kiriting.", 'challenge_invalid', 422);
        }
    }

    /**
     * @throws AdminAuthException
     */
    private function codeFailed(User $user, Request $request, AdminAudit $audit, string $action, string $reason = 'invalid_code'): never
    {
        $locked = $this->lockout->fail(AdminLoginLockout::CODE, AdminLoginLockout::accountForUser($user->id), $request->ip());
        $audit->action($action)->meta(['failure' => $reason, 'locked' => $locked]);

        throw new AdminAuthException(
            $reason === 'invalid_recovery_code' ? "Zaxira kodi noto'g'ri yoki ishlatilgan." : "Kod noto'g'ri. Ilovadagi yangi kodni kiriting.",
            $reason,
            422,
        );
    }

    /**
     * @throws AdminAuthException
     */
    private function refuseWhenLocked(string $kind, string $account, Request $request, AdminAudit $audit): void
    {
        $seconds = $this->lockout->lockedFor($kind, $account, $request->ip());
        if ($seconds <= 0) {
            return;
        }

        $audit->action('admin.auth.locked_out')->meta(['kind' => $kind, 'retry_after' => $seconds]);
        $minutes = (int) ceil($seconds / 60);

        throw new AdminAuthException(
            "Juda ko'p noto'g'ri urinish. {$minutes} daqiqadan so'ng qayta urinib ko'ring.",
            'locked',
            429,
            ['retry_after' => $seconds],
            ['Retry-After' => (string) $seconds],
        );
    }
}
