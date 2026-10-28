<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Customers\CustomerAdminException;
use App\Services\Admin\Customers\UserBlocking;
use App\Services\Admin\Customers\UserCardPresenter;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-31: actions on a user from the user card: admin.support.manage, a step-up (admin.recent) and a `reason` each,
 * audited with the account as the subject (the route name is the action). Never on a RIZQIM team account (403
 * platform_account): Jamoa disables those.
 */
#[Group('Admin')]
class AdminCustomerActionController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly UserBlocking $blocking,
        private readonly UserCardPresenter $presenter,
    ) {}

    /**
     * Block a user
     *
     * The account signs in nowhere (Telegram, SMS code, refresh, staff PIN: 403 `account_blocked`), its tokens stop
     * working and every session ends now, so it cannot order; a blocked staff member's Telegram order buttons stop too.
     * A blocked owner's store keeps working (suspend the store in Ovqatxonalar). `reason` is required and shown on the
     * card. Answers the card. 409 `already_blocked`, 403 `platform_account`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'Already blocked (already_blocked)', type: self::ERROR)]
    #[ApiDocResponse(403, 'A RIZQIM team account (platform_account)', type: self::ERROR)]
    public function block(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->act($request, $id, function (User $user) use ($request, $data) {
            $result = $this->blocking->block($user, $this->member($request), $data['reason']);
            AdminAudit::for($request)->meta(['sessions_closed' => $result['sessions']]);

            return $result;
        }, 'Foydalanuvchi bloklandi. Uning barcha sessiyalari yopildi.');
    }

    /**
     * Unblock a user
     *
     * Clears the block; the person signs in again. `reason` is required. Answers the card. 409 `not_blocked`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'Not blocked (not_blocked)', type: self::ERROR)]
    public function unblock(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->act($request, $id, fn (User $user) => $this->blocking->unblock($user), 'Blok olib tashlandi.');
    }

    /**
     * Close all sessions
     *
     * Ends every session of the account: Mini App and app tokens, refresh tokens, staff PIN sessions on terminals (a
     * terminal's own pairing stays); its devices stop getting pushes. The person can sign in again (unless blocked).
     * `reason` is required. Answers the card; the counts are in the audit entry.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function closeSessions(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->act($request, $id, function (User $user) use ($request) {
            $closed = $this->blocking->closeSessions($user);
            AdminAudit::for($request)->meta(['sessions_closed' => $closed]);

            return ['before' => [], 'after' => [], 'sessions' => $closed];
        }, 'Barcha sessiyalar yopildi.');
    }

    /**
     * @param  callable(User): array<string, mixed>  $action
     */
    private function act(Request $request, int $id, callable $action, string $message): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return $this->refuse('Foydalanuvchi topilmadi.', 'not_found', 404);
        }

        try {
            $change = $action($user);
        } catch (CustomerAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($user)->store($user->store_id !== null ? (int) $user->store_id : null)
            ->changes($change['before'] ?? [], $change['after'] ?? []);

        return $this->success($this->presenter->card($user->refresh(), $this->member($request)), $message);
    }
}
