<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminSessionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * P4-11: Sessiyalarim — the signed-in member's own admin sessions (one per sign-in): where and when, which one is this.
 */
#[Group('Admin')]
class AdminSessionController extends AdminController
{
    public function __construct(
        private readonly AdminSessionService $sessions,
    ) {}

    /**
     * My admin sessions
     *
     * Live sessions only (not past their 12 hours, used in the last 60 minutes), newest first; `current` is this one.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{id: int, ip: string|null, user_agent: string|null, created_at: string|null, last_used_at: string|null, expires_at: string|null, reauthenticated_until: string|null, current: bool}>}')]
    public function index(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $current = $this->sessions->current($member);

        return $this->success($this->sessions->sessionsOf($member)->map(fn (PersonalAccessToken $token) => $this->sessions->present($token, $current))->all());
    }

    /**
     * End one of my sessions
     *
     * Ending this session is a log-out; ending another one needs a step-up confirmation (403 `reauth_required`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, never>}')]
    #[ApiDocResponse(403, 'Step-up needed for another session (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(404, 'No such live session of mine', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        $session = $this->sessions->find($member, $id);
        if ($session === null) {
            return $this->refuse('Sessiya topilmadi.', 'not_found', 404);
        }

        $current = $this->sessions->current($member);
        $isCurrent = $current !== null && (int) $current->getKey() === (int) $session->getKey();

        if (!$isCurrent && ($current === null || $this->sessions->reauthenticatedUntil($current) === null)) {
            return $this->refuse('Bu amal uchun 2FA kodini qayta kiriting.', 'reauth_required', 403);
        }

        AdminAudit::for($request)->meta(['session_id' => $session->getKey(), 'current' => $isCurrent, 'ip' => $session->getAttribute('ip_address')]);
        $session->delete();

        return $this->success([], 'Sessiya yakunlandi.');
    }
}
