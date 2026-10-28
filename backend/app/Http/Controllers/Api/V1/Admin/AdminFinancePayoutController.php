<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\PayoutRequest;
use App\Services\Admin\AdminAudit;
use App\Services\Finance\Payouts\PayoutException;
use App\Services\Finance\Payouts\PayoutPresenter;
use App\Services\Finance\Payouts\PayoutService;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-23: Moliya → Payoutlar. Reads need admin.finance.view; approving and rejecting admin.payouts.approve, a step-up
 * and a `reason`, with an Idempotency-Key. Approving nets the store's debt first (the oldest subscription invoices,
 * then the commission debt) and transfers the rest; 1 000 000 so'm or more needs two different members
 * (App\Services\Finance\Payouts\PayoutService). Audited on the payout (admin.finance.payouts.approved / .rejected).
 */
#[Group('Admin')]
class AdminFinancePayoutController extends AdminController
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly PayoutPresenter $presenter,
    ) {}

    /**
     * Payout queue
     *
     * `status`: open (the default: pending and awaiting_second), pending (no approval yet), awaiting_second (approved
     * once, waits for another member), completed, rejected, all; `store_id`. Oldest open first, else newest first;
     * cursor pages (`per_page` 1–100, default 30). `counts` — open requests and their amount.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PayoutPresenter::ROW . '>, next_cursor: string|null, per_page: int, counts: array{pending: int, awaiting_second: int, open_amount: string}, threshold: string}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'pending', 'awaiting_second', 'completed', 'rejected', 'all'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $status = $data['status'] ?? 'open';
        $open = in_array($status, ['open', 'pending', 'awaiting_second'], true);

        $page = PayoutRequest::query()
            ->when($status === 'open', fn (Builder $query) => $query->where('status', 'pending'))
            ->when($status === 'pending', fn (Builder $query) => $query->where('status', 'pending')->whereNull('first_approved_by'))
            ->when($status === 'awaiting_second', fn (Builder $query) => $query->where('status', 'pending')->whereNotNull('first_approved_by'))
            ->when(in_array($status, ['completed', 'rejected'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(isset($data['store_id']), fn (Builder $query) => $query->where('store_id', (int) $data['store_id']))
            ->orderBy('id', $open ? 'asc' : 'desc')
            ->cursorPaginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        $pending = PayoutRequest::query()->where('status', 'pending');

        return $this->success([
            'data' => collect($page->items())->map(fn (PayoutRequest $payout) => $this->presenter->row($payout))->values()->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'counts' => [
                'pending' => (clone $pending)->whereNull('first_approved_by')->count(),
                'awaiting_second' => (clone $pending)->whereNotNull('first_approved_by')->count(),
                'open_amount' => Money::of((string) (clone $pending)->sum('amount'))->toString(),
            ],
            'threshold' => PayoutService::threshold()->toString(),
        ]);
    }

    /**
     * Payout card
     *
     * The row plus `breakdown` — "Hisoblangan — Ushlangan qarz — O'tkaziladi": `accrued` (asked for), `netted` (the
     * store's debt taken first) and `transfer` (to the card), `lines` (each invoice or the commission debt it closed,
     * with the ledger row). For an open payout it is what approving now would do (`preview: true`); `balances` —
     * the store's wallet and debt now; `actions`. The card number is full with admin.pii.view (audited), else masked.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PayoutPresenter::CARD . '}')]
    public function show(Request $request, int $id): JsonResponse
    {
        $payout = PayoutRequest::find($id);
        if ($payout === null) {
            return $this->refuse("So'rov topilmadi.", 'not_found', 404);
        }

        return $this->success($this->presenter->card($payout, $this->member($request)));
    }

    /**
     * Approve a payout
     *
     * `reason` required. Under 1 000 000 so'm: pays it out now. From 1 000 000 so'm: the first approval is recorded
     * (`status: awaiting_second`), a different member's approval pays it out (403 `four_eyes` for the first approver,
     * a superadmin too). Paying out nets the store's debt first, then transfers the rest; 422 `insufficient_wallet`
     * when the wallet cannot cover the amount (nothing is booked). A decided payout: 409 `payout_decided`; the same
     * member repeating the approval gets the same answer and nothing runs twice (also with the same Idempotency-Key).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PayoutPresenter::CARD . '}')]
    #[ApiDocResponse(403, 'The first approver cannot complete it (four_eyes) or step-up needed (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'Already decided (payout_decided)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(422, 'The wallet cannot cover it (insufficient_wallet)', type: 'array{success: false, message: string, code: string, data: array{reason: string, wallet_balance?: string}}')]
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, true);
    }

    /**
     * Reject a payout
     *
     * `reason` (why; the owner sees it as the resolution note) required. Open payouts only (409 `payout_decided`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PayoutPresenter::CARD . '}')]
    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, false);
    }

    private function decide(Request $request, int $id, bool $approve): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $payout = PayoutRequest::find($id);
        if ($payout === null) {
            return $this->refuse("So'rov topilmadi.", 'not_found', 404);
        }

        $member = $this->member($request);
        try {
            $result = $approve
                ? $this->payouts->approve($payout, $member, $data['reason'])
                : $this->payouts->reject($payout, $member, $data['reason']);
        } catch (PayoutException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $payout = $result['payout'];
        $stage = PayoutService::stage($payout);
        $audit = AdminAudit::for($request)->subject($payout)->store((int) $payout->store_id);
        if ($result['replayed']) {
            $audit->skip();
        } else {
            $audit->changes(['status' => $result['stage_before']], ['status' => $stage])->meta(array_filter([
                'amount' => Money::of($payout->amount)->toString(),
                'netted' => $payout->netted_amount !== null ? Money::of($payout->netted_amount)->toString() : null,
                'transferred' => $payout->transferred_amount !== null ? Money::of($payout->transferred_amount)->toString() : null,
                'netting' => $stage === PayoutService::COMPLETED ? $payout->netting : null,
                'first_approved_by' => $payout->first_approved_by !== null ? (int) $payout->first_approved_by : null,
            ], fn ($value) => $value !== null));
        }

        $message = match ($stage) {
            PayoutService::AWAITING_SECOND => "Birinchi tasdiq yozildi. Payoutni boshqa xodim tasdiqlashi kerak.",
            PayoutService::COMPLETED => 'Tasdiqlandi: ' . PayoutService::uzs(Money::of($payout->transferred_amount ?? $payout->amount)) . " kartaga o'tkaziladi"
                . (Money::of($payout->netted_amount ?? 0)->isPositive() ? ', ' . PayoutService::uzs(Money::of($payout->netted_amount)) . ' qarzga ushlandi.' : '.'),
            default => "So'rov rad etildi.",
        };

        return $this->success($this->presenter->card($payout, $member), $message);
    }
}
