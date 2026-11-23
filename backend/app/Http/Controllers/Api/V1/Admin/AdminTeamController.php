<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminSessionService;
use App\Services\Admin\PlatformTeamService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-11: Tizim → Jamoa (admin.team.manage): the RIZQIM team, invites, roles, disabling, 2FA resets. Every change
 * needs a step-up confirmation (`admin.recent`) and is recorded in the audit log.
 *
 * Wave P: a member holds a set of roles. Requests send `platform_roles` (a list, at least one role, no repeats); the
 * legacy single `platform_role` is still accepted and means a one-role set (never both at once). Answers carry
 * `platform_roles` and `platform_role_labels` (PlatformRole::cases() order) and, for old clients, the primary role in
 * `platform_role` / `platform_role_label` (the first of the set).
 */
#[Group('Admin')]
class AdminTeamController extends AdminController
{
    public function __construct(
        private readonly PlatformTeamService $team,
        private readonly AdminSessionService $sessions,
    ) {}

    /**
     * The team
     *
     * Every team member: roles (`platform_roles`), `status` (`invited`: has not accepted the invite yet, `active`, `disabled`), 2FA, last
     * sign-in and live sessions.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{id: int, name: string, email: string|null, platform_roles: list<string>, platform_role_labels: list<string>, platform_role: string|null, platform_role_label: string|null, status: string, disabled: bool, two_factor_enabled: bool, last_login_at: string|null, active_sessions: int, invite_expires_at: string|null, created_at: string|null}>}')]
    public function index(Request $request): JsonResponse
    {
        $members = User::query()->whereNotNull('platform_role')->orderBy('name')->orderBy('id')->get();

        return $this->success($members->map(fn (User $member) => $this->present($member))->all());
    }

    /**
     * One team member
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, name: string, email: string|null, platform_roles: list<string>, platform_role_labels: list<string>, platform_role: string|null, platform_role_label: string|null, status: string, disabled: bool, two_factor_enabled: bool, last_login_at: string|null, active_sessions: int, invite_expires_at: string|null, created_at: string|null}}')]
    #[ApiDocResponse(404, 'Not a team member', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(int $id): JsonResponse
    {
        $member = $this->find($id);

        return $member ? $this->success($this->present($member)) : $this->notFound();
    }

    /**
     * Invite a team member
     *
     * Creates the member (they cannot sign in until they accept) and a one-time invite link valid 72 hours:
     * `invite_url` is shown only in this answer. `platform_roles`: one or more roles (or the legacy `platform_role`).
     * Only a superadmin may invite a superadmin, or with any role that holds team.manage, pii.view, system.manage,
     * stores.impersonate, orders.refund_approve or payouts.approve among the roles (403 `superadmin_only`). Accepting
     * opens a 24 hour window for the 2FA set-up. Needs step-up.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{member: array{id: int, name: string, email: string|null, platform_roles: list<string>, platform_role_labels: list<string>, platform_role: string|null, platform_role_label: string|null, status: string, disabled: bool, two_factor_enabled: bool, last_login_at: string|null, active_sessions: int, invite_expires_at: string|null, created_at: string|null}, invite_url: string, invite_token: string, invite_expires_at: string}}')]
    #[ApiDocResponse(422, 'Email taken (email_taken) or invalid input', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required) or superadmin only (superadmin_only)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|string|email|max:255',
            // Wave P: one or more distinct roles; the legacy single `platform_role` instead (never both)
            'platform_roles' => ['required_without:platform_role', 'prohibits:platform_role', 'array', 'min:1', 'max:' . count(PlatformRole::cases())],
            'platform_roles.*' => ['required', 'string', 'distinct', Rule::enum(PlatformRole::class)],
            'platform_role' => ['required_without:platform_roles', Rule::enum(PlatformRole::class)],
        ], UzValidation::messages());

        $result = $this->team->invite($data['name'], $data['email'], self::roles($data) ?? [], $this->member($request));
        $member = $result['member'];

        AdminAudit::for($request)->subject($member)->changes([], [
            'name' => $member->name,
            'email' => $member->email,
            'platform_roles' => array_map(fn (PlatformRole $role) => $role->value, $member->platformRoles()),
        ]);

        return $this->success([
            'member' => $this->present($member),
            'invite_url' => PlatformTeamService::inviteUrl($result['token']),
            'invite_token' => $result['token'],
            'invite_expires_at' => $result['expires_at']->toIso8601String(),
        ], "Taklif yaratildi. Havolani faqat hozir ko'rasiz.", 201);
    }

    /**
     * Re-issue the invite
     *
     * A new link for a member who has not accepted yet (409 `invite_already_accepted` otherwise); the older links stop
     * working. A member holding superadmin or a privileged role among their roles: superadmin only (403 `superadmin_only`). Needs step-up.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{invite_url: string, invite_token: string, invite_expires_at: string}}')]
    #[ApiDocResponse(409, 'Already accepted (invite_already_accepted)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required) or superadmin only (superadmin_only)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function reissueInvite(Request $request, int $id): JsonResponse
    {
        $member = $this->find($id);
        if ($member === null) {
            return $this->notFound();
        }

        $result = $this->team->reissueInvite($member, $this->member($request));
        AdminAudit::for($request)->subject($member)->meta(['invite_expires_at' => $result['expires_at']->toIso8601String()]);

        return $this->success([
            'invite_url' => PlatformTeamService::inviteUrl($result['token']),
            'invite_token' => $result['token'],
            'invite_expires_at' => $result['expires_at']->toIso8601String(),
        ], "Yangi taklif havolasi yaratildi.");
    }

    /**
     * Change a member's roles or disable / enable them
     *
     * `platform_roles` (the new set as a whole, at least one role; or the legacy single `platform_role`, which
     * replaces the set with that one role) and/or `disabled`. Disabling ends all of the member's sessions. Nobody
     * changes their own roles or disables themselves (`cannot_change_self`), the last superadmin somebody can sign in
     * as (active, password, 2FA on) keeps superadmin and stays enabled (`last_superadmin`), and only a superadmin
     * changes a member holding superadmin or a privileged role (team.manage, pii.view, system.manage,
     * stores.impersonate, refund or payout approval) among their roles, or gives a set holding one
     * (`superadmin_only`). Needs step-up.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, name: string, email: string|null, platform_roles: list<string>, platform_role_labels: list<string>, platform_role: string|null, platform_role_label: string|null, status: string, disabled: bool, two_factor_enabled: bool, last_login_at: string|null, active_sessions: int, invite_expires_at: string|null, created_at: string|null}}')]
    #[ApiDocResponse(422, 'cannot_change_self, last_superadmin or invalid input', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required) or superadmin only (superadmin_only)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            // Wave P: the new set as a whole; the legacy single `platform_role` instead (never both)
            'platform_roles' => ['sometimes', 'required', 'prohibits:platform_role', 'array', 'min:1', 'max:' . count(PlatformRole::cases())],
            'platform_roles.*' => ['required', 'string', 'distinct', Rule::enum(PlatformRole::class)],
            'platform_role' => ['sometimes', 'required', Rule::enum(PlatformRole::class)],
            'disabled' => 'sometimes|required|boolean',
        ], UzValidation::messages());

        $member = $this->find($id);
        if ($member === null) {
            return $this->notFound();
        }

        $change = $this->team->update(
            $member,
            self::roles($data),
            array_key_exists('disabled', $data) ? (bool) $data['disabled'] : null,
            $this->member($request),
        );

        $audit = AdminAudit::for($request)->subject($member)->changes($change['before'], $change['after']);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->present($member), "O'zgarish saqlandi.");
    }

    /**
     * Reset a member's 2FA
     *
     * For a lost phone: 2FA and the recovery codes are removed and every session of the member ends; they set 2FA up
     * again at a sign-in within 24 hours (after that the set-up is refused, `setup_not_allowed`: reset again). Not for
     * yourself; a member holding superadmin or a privileged role among their roles: superadmin only. Needs step-up.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, name: string, email: string|null, platform_roles: list<string>, platform_role_labels: list<string>, platform_role: string|null, platform_role_label: string|null, status: string, disabled: bool, two_factor_enabled: bool, last_login_at: string|null, active_sessions: int, invite_expires_at: string|null, created_at: string|null}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required) or superadmin only (superadmin_only)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function resetTwoFactor(Request $request, int $id): JsonResponse
    {
        $member = $this->find($id);
        if ($member === null) {
            return $this->notFound();
        }

        $before = $member->hasTwoFactorEnabled();
        $this->team->resetTwoFactor($member, $this->member($request));
        AdminAudit::for($request)->subject($member)->changes(['two_factor_enabled' => $before], ['two_factor_enabled' => false])
            ->meta(['sessions_ended' => true]);

        return $this->success($this->present($member->refresh()), "2FA o'chirildi. A'zo keyingi kirishda uni qayta o'rnatadi.");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<PlatformRole>|null  null: no role change asked
     */
    private static function roles(array $data): ?array
    {
        if (isset($data['platform_roles'])) {
            return User::normalizePlatformRoles($data['platform_roles']);
        }

        return isset($data['platform_role']) ? [PlatformRole::from($data['platform_role'])] : null;
    }

    private function find(int $id): ?User
    {
        return User::query()->whereNotNull('platform_role')->find($id);
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse("Jamoa a'zosi topilmadi.", 'not_found', 404);
    }

    /**
     * @return array{id: int, name: string, email: string|null, platform_roles: list<string>, platform_role_labels: list<string>, platform_role: string|null, platform_role_label: string|null, status: string, disabled: bool, two_factor_enabled: bool, last_login_at: string|null, active_sessions: int, invite_expires_at: string|null, created_at: string|null}
     */
    private function present(User $member): array
    {
        $status = PlatformTeamService::status($member);

        return [
            'id' => (int) $member->id,
            'name' => (string) $member->name,
            'email' => $member->email,
            'platform_roles' => array_map(fn (PlatformRole $role) => $role->value, $member->platformRoles()),
            'platform_role_labels' => array_map(fn (PlatformRole $role) => $role->label(), $member->platformRoles()),
            'platform_role' => $member->platform_role?->value,
            'platform_role_label' => $member->platform_role?->label(),
            'status' => $status,
            'disabled' => $member->platform_disabled_at !== null,
            'two_factor_enabled' => $member->hasTwoFactorEnabled(),
            'last_login_at' => $member->platform_last_login_at?->toIso8601String(),
            'active_sessions' => $this->sessions->activeCount($member),
            'invite_expires_at' => $status === 'invited' ? $this->team->pendingInvite($member)?->expires_at->toIso8601String() : null,
            'created_at' => $member->created_at?->toIso8601String(),
        ];
    }
}
