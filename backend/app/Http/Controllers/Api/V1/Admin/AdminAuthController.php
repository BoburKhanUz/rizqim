<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\AdminLoginChallenge;
use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminAuthException;
use App\Services\Admin\AdminAuthService;
use App\Services\Admin\AdminSessionService;
use App\Services\Admin\PlatformTeamService;
use App\Services\Admin\TwoFactorService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * P4-11: signing in to the Boshqaruv markazi: email + password, then a TOTP code (Google Authenticator); the first
 * time, the 2FA set-up. Every attempt is recorded in the audit log without secrets. 5 bad passwords or codes per
 * account and address lock it for 15 minutes (429 `locked`).
 */
#[Group('Admin', "Boshqaruv markazi (RIZQIM jamoasi): kirish, 2FA, jamoa, rollar, jurnal, qidiruv. Alohida `admin` token.")]
class AdminAuthController extends AdminController
{
    public function __construct(
        private readonly AdminAuthService $auth,
        private readonly AdminSessionService $sessions,
        private readonly TwoFactorService $twoFactor,
        private readonly PlatformTeamService $team,
    ) {}

    /**
     * Admin sign-in: email and password
     *
     * Returns a challenge for the second step (5 minutes, single use): `otp_required` (send the app's code to
     * `2fa/verify`) or `setup_required` (2FA not set up yet: `2fa/setup`, then `2fa/confirm`). An unknown email and a
     * wrong password get the same 422 `invalid_credentials`; a disabled member 403 `member_disabled`; too many bad
     * tries 429 `locked`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{status: string, challenge_token: string, expires_at: string}}')]
    #[ApiDocResponse(422, 'Wrong email or password (invalid_credentials)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(403, 'Disabled member (member_disabled)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(429, 'Locked for 15 minutes (locked)', type: 'array{success: false, message: string, code: string, data: array{reason: string, retry_after: int}}')]
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:200',
        ], UzValidation::messages());

        $challenge = $this->auth->login($data['email'], $data['password'], $request, AdminAudit::for($request));

        return $this->challengeAnswer($challenge);
    }

    /**
     * Start the 2FA set-up
     *
     * Only for a `setup_required` challenge. Returns the new secret (for manual entry), the `otpauth://` URI and the
     * QR code as inline SVG. The same challenge gives the same secret again; it is saved only when `2fa/confirm`
     * accepts a code from it, and never shown again afterwards. 409 `two_factor_already_enabled` once 2FA is on.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{secret: string, otpauth_uri: string, qr_svg: string}}')]
    #[ApiDocResponse(409, '2FA is already on (two_factor_already_enabled)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(422, 'Unknown, used or expired challenge (challenge_invalid)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function setup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => 'required|string|max:200',
        ], UzValidation::messages());

        return $this->success($this->auth->setup($data['challenge_token'], $request, AdminAudit::for($request)));
    }

    /**
     * Confirm the 2FA set-up
     *
     * The first code from the authenticator app turns 2FA on and opens the session. `recovery_codes` (10) are shown
     * only here, once: each one signs in once when the phone is lost; only their hashes are kept.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, expires_at: string, member: array{id: int, name: string, email: string|null, platform_role: string|null, platform_role_label: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null}, recovery_codes: list<string>}}')]
    #[ApiDocResponse(422, 'Wrong code (invalid_code) or challenge (challenge_invalid)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'Set-up not started or 2FA already on', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(429, 'Locked for 15 minutes (locked)', type: 'array{success: false, message: string, code: string, data: array{reason: string, retry_after: int}}')]
    public function confirmSetup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => 'required|string|max:200',
            'code' => AdminRules::code(),
        ], UzValidation::messages(['code.regex' => "Kod 6 ta raqamdan iborat bo'lishi kerak."]));

        $result = $this->auth->confirmSetup($data['challenge_token'], $data['code'], $request, AdminAudit::for($request));

        return $this->success([
            ...$this->sessionAnswer($result['session'], $result['user']),
            'recovery_codes' => $result['recovery_codes'],
        ], '2FA yoqildi.');
    }

    /**
     * Second step: the 2FA code
     *
     * Send `code` (6 digits from the app) or `recovery_code` (one of the saved recovery codes: it is burnt). A code
     * already used, or one older than the last used, is refused (replay). Opens the session: `token` (Bearer, the
     * admin API only), absolute `expires_at` (12 hours); 60 minutes without a request also end it.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, expires_at: string, member: array{id: int, name: string, email: string|null, platform_role: string|null, platform_role_label: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null}}}')]
    #[ApiDocResponse(422, 'Wrong code (invalid_code, invalid_recovery_code) or challenge (challenge_invalid)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'The account must set 2FA up first (setup_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(429, 'Locked for 15 minutes (locked)', type: 'array{success: false, message: string, code: string, data: array{reason: string, retry_after: int}}')]
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => 'required|string|max:200',
            'code' => [...AdminRules::code(false), 'required_without:recovery_code'],
            'recovery_code' => 'nullable|string|max:32',
        ], UzValidation::messages([
            'code.regex' => "Kod 6 ta raqamdan iborat bo'lishi kerak.",
            'code.required_without' => '2FA kodi yoki zaxira kodini kiriting.',
        ]));

        $session = $this->auth->verify($data['challenge_token'], $data['code'] ?? null, $data['recovery_code'] ?? null, $request, AdminAudit::for($request));

        /** @var User $user */
        $user = $session->accessToken->tokenable;

        return $this->success($this->sessionAnswer($session, $user));
    }

    /**
     * Accept an invite
     *
     * The invite link's token and the new password (at least 12 characters, not the email). Answers with a
     * `setup_required` challenge: the member goes straight on to the 2FA set-up. 422 `invite_invalid` for an
     * unknown, used, re-issued or expired (72 hours) link.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{status: string, challenge_token: string, expires_at: string}}')]
    #[ApiDocResponse(422, 'Invalid invite (invite_invalid) or password', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function acceptInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:200',
            'password' => AdminRules::password(),
        ], UzValidation::messages(['password.min' => "Parol kamida 12 belgidan iborat bo'lishi kerak."]));

        $audit = AdminAudit::for($request)->recordAlways()->action('admin.auth.invite_failed')->actor(null);

        try {
            $member = $this->team->acceptInvite($data['token'], $data['password']);
        } catch (AdminAuthException $e) {
            $audit->meta(['failure' => $e->reason]);
            throw $e;
        } catch (ValidationException $e) {
            $audit->meta(['failure' => 'password_rejected']);
            throw $e;
        }

        $audit->action('admin.auth.invite_accepted')->actor($member)->subject($member);

        $purpose = $member->hasTwoFactorEnabled() ? AdminLoginChallenge::PURPOSE_OTP : AdminLoginChallenge::PURPOSE_SETUP;

        return $this->challengeAnswer($this->auth->issueChallenge($member, $purpose, $request), 'Parol saqlandi.');
    }

    /**
     * Log out (this session)
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, never>}')]
    public function logout(Request $request): JsonResponse
    {
        $token = $this->sessions->current($this->member($request));
        if ($token !== null) {
            AdminAudit::for($request)->meta(['session_id' => $token->getKey()]);
            $token->delete();
        }

        return $this->success([], 'Tizimdan chiqildi.');
    }

    /**
     * The signed-in member
     *
     * Who is signed in, their platform role and effective admin permissions (the admin app shows only the sections
     * these allow), 2FA state and the current session (`reauthenticated_until`: the step-up confirmation, if any).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{member: array{id: int, name: string, email: string|null, platform_role: string|null, platform_role_label: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null}, platform_role: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null, session: array{id: int, ip: string|null, user_agent: string|null, created_at: string|null, last_used_at: string|null, expires_at: string|null, reauthenticated_until: string|null, current: bool}|null}}')]
    public function me(Request $request): JsonResponse
    {
        return $this->success($this->mePayload($request));
    }

    /**
     * The signed-in member (alias of GET /admin/auth/me)
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{member: array{id: int, name: string, email: string|null, platform_role: string|null, platform_role_label: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null}, platform_role: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null, session: array{id: int, ip: string|null, user_agent: string|null, created_at: string|null, last_used_at: string|null, expires_at: string|null, reauthenticated_until: string|null, current: bool}|null}}')]
    public function currentMember(Request $request): JsonResponse
    {
        return $this->success($this->mePayload($request));
    }

    /**
     * Step-up: confirm with a fresh code
     *
     * A current TOTP code marks this session confirmed for 10 minutes. Actions behind it (team and role changes,
     * 2FA resets, new recovery codes, revoking another session; later money and order interventions) answer 403
     * `reauth_required` without it: ask for the code, call this, retry once.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{reauthenticated_until: string}}')]
    #[ApiDocResponse(422, 'Wrong code (invalid_code)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(429, 'Locked for 15 minutes (locked)', type: 'array{success: false, message: string, code: string, data: array{reason: string, retry_after: int}}')]
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => AdminRules::code(),
        ], UzValidation::messages(['code.regex' => "Kod 6 ta raqamdan iborat bo'lishi kerak."]));

        $until = $this->auth->stepUp($this->member($request), $data['code'], $request, AdminAudit::for($request));

        return $this->success(['reauthenticated_until' => $until->toIso8601String()], 'Tasdiqlandi.');
    }

    /**
     * New recovery codes
     *
     * Replaces every recovery code with 10 new ones, shown only in this answer. Needs a step-up confirmation.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{recovery_codes: list<string>}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $codes = $this->twoFactor->regenerateRecoveryCodes($member);
        AdminAudit::for($request)->subject($member)->meta(['count' => count($codes)]);

        return $this->success(['recovery_codes' => $codes], 'Yangi zaxira kodlari yaratildi.');
    }

    /**
     * @return array{member: array<string, mixed>, platform_role: string|null, permissions: list<string>, two_factor_enabled: bool, recovery_codes_left: int, last_login_at: string|null, session: array<string, mixed>|null}
     */
    private function mePayload(Request $request): array
    {
        $member = $this->member($request);
        $payload = $this->memberPayload($member);
        $token = $this->sessions->current($member);

        return [
            'member' => $payload,
            'platform_role' => $payload['platform_role'],
            'permissions' => $payload['permissions'],
            'two_factor_enabled' => $payload['two_factor_enabled'],
            'recovery_codes_left' => $payload['recovery_codes_left'],
            'last_login_at' => $payload['last_login_at'],
            'session' => $token !== null ? $this->sessions->present($token, $token) : null,
        ];
    }

    private function challengeAnswer(AdminLoginChallenge $challenge, string $message = ''): JsonResponse
    {
        return $this->success([
            'status' => $challenge->purpose === AdminLoginChallenge::PURPOSE_OTP ? 'otp_required' : 'setup_required',
            'challenge_token' => (string) $challenge->getAttribute('plain_token'),
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ], $message);
    }

    /**
     * @return array{token: string, expires_at: string|null, member: array<string, mixed>}
     */
    private function sessionAnswer(NewAccessToken $session, User $user): array
    {
        return [
            'token' => $session->plainTextToken,
            'expires_at' => $session->accessToken->expires_at?->toIso8601String(),
            'member' => $this->memberPayload($user->refresh()),
        ];
    }
}
