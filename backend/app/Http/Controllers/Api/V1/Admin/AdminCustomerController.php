<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Customers\UserCardPresenter;
use App\Services\Admin\Customers\UserDirectory;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-31: Mijozlar va yordam → Foydalanuvchilar (admin.users.view): customers, couriers and store staff of the whole
 * platform and the user card. RIZQIM team accounts are not here (Jamoa). Phones are masked unless admin.pii.view (then
 * every number shown is an audited reveal).
 */
#[Group('Admin')]
class AdminCustomerController extends AdminController
{
    private const ROW = 'array{id: int, name: string, role: string, role_label: string, phone: string|null, telegram_linked: bool, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, orders_count: int, blocked: bool, blocked_at: string|null, created_at: string|null}';

    public function __construct(
        private readonly UserDirectory $directory,
        private readonly UserCardPresenter $presenter,
    ) {}

    /**
     * Users
     *
     * Newest first, cursor pages (`cursor` from `next_cursor`, `per_page` 1–100, default 30). Filters: `role`
     * (customer, courier, staff — every store role but the courier —, owner, manager, cashier, waiter, cook),
     * `status` (active, blocked), `store_id`, `q`: a part of the name, or a phone — without admin.pii.view only the
     * whole number (`+998901234567`, `90 123 45 67`...), with it any 4+ digits. `roles`: the role filter's words.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, next_cursor: string|null, per_page: int, roles: array<string, string>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|min:2|max:100',
            'role' => ['nullable', Rule::in(UserDirectory::ROLE_FILTERS)],
            'status' => ['nullable', Rule::in(['active', 'blocked'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $member = $this->member($request);
        $page = $this->directory->page($member, $filters);

        return $this->success([
            'data' => $this->directory->rows($member, $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'roles' => UserDirectory::roleLabels(),
        ]);
    }

    /**
     * User card
     *
     * `profile` (phone masked unless admin.pii.view; a reveal is audited), `block` (blocked, when, by whom, why),
     * `stats` (orders, delivered, cancelled, delivered total, average check, last order, segment), `orders` (the last
     * 20, links to /orders/{uuid}) and `refunds` (refund requests for their orders) — null without admin.orders.view;
     * `keshbek` (`balance` and its movements read back from payments and referral rewards), `complaints` (null without
     * admin.support.view), `sessions` (live tokens — never an admin session —, refresh sessions, last use, the
     * person's devices), `membership` (a store staff account's store, branch, role, login phone; null for customers),
     * `linked_accounts` (other accounts with the same phone or Telegram), `audit` (entries about this account; null
     * without admin.audit.view), `actions` (block, unblock, close_sessions: admin.support.manage). A RIZQIM team account
     * is 404 here.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such user, or a RIZQIM team account (not_found)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->directory->find($id);
        if ($user === null) {
            return $this->refuse('Foydalanuvchi topilmadi.', 'not_found', 404);
        }

        return $this->success($this->presenter->card($user, $this->member($request)));
    }
}
