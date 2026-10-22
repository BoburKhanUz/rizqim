<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Enums\Permission;
use App\Services\Auth\AuthTokenException;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P3-18: a manager's one-time approval on a terminal (POS_SPEC 2.2).
 */
#[Group('POS: billing', "Kassa: schyot, bo'lish, to'lov, qaytarish, smena, X/Z va menejer tasdig'i (P3-18).")]
class PosApprovalController extends PosBillingController
{
    /**
     * Manager approval (PIN on the terminal)
     *
     * The staff member signed in on the terminal (a staff PIN session) lacks a permission for an action (a void after
     * the bill, a discount, a reprint, a refund, cash out, nasiya...): a manager types their own staff id (or staff
     * code) and PIN on that terminal. The answer is a one-time `token` for that one `permission` on that terminal,
     * valid 60 seconds: send it as `approval_token` with the action. The PIN check and lockout are the sign-in's
     * (wrong PIN 422 `pin_invalid`, locked 429 `pin_locked` with Retry-After); a manager without the permission 403
     * `approver_not_allowed`. Only from a staff PIN session on a terminal (422 `terminal_required` otherwise). Both
     * people are in the audit log (`pos.approval_granted`, and `pos.approval_used` when the action consumes it).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{token: string, permission: string, expires_at: string, approved_by: array{id: int, name: string, role: string}}}')]
    public function store(Request $request, ManagerApprovalService $approvals): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'permission' => ['required', 'string', Rule::in(ManagerApprovalService::approvable())],
            'user_id' => 'required_without:staff_code|nullable|integer|min:1|max:999999999999',
            'staff_code' => 'required_without:user_id|nullable|string|max:8',
            'pin' => 'required|string|max:16',
        ]);

        $device = $this->terminal($user);
        if ($device === null) {
            return $this->error("Menejer tasdig'i faqat kassa terminalida (xodim PIN sessiyasida) beriladi.", ['reason' => 'terminal_required'], 422);
        }

        try {
            $approval = $approvals->grant(
                $user,
                $device,
                Permission::from($data['permission']),
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                $data['staff_code'] ?? null,
                (string) $data['pin'],
            );
        } catch (AuthTokenException $e) {
            $response = $this->error($e->getMessage(), $e->data(), $e->status);

            return $e->retryAfter ? $response->header('Retry-After', (string) $e->retryAfter) : $response;
        }

        return $this->success([
            'token' => $approval['token'],
            'permission' => $approval['permission'],
            'expires_at' => $approval['expires_at']->toIso8601String(),
            'approved_by' => [
                'id' => (int) $approval['manager']->id,
                'name' => (string) $approval['manager']->name,
                'role' => $approval['manager']->role->value,
            ],
        ], 'Tasdiqlandi.', 201);
    }
}
