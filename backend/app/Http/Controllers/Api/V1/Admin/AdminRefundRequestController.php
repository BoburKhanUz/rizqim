<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Orders\AdminOrderException;
use App\Services\Admin\Orders\AdminOrderPresenter;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\RefundRequestService;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-14: refund requests with four-eyes approval (App\Services\Admin\Orders\RefundRequestService). Asking needs
 * admin.orders.refund_request, deciding admin.orders.refund_approve, both a step-up and a `reason`; the one who asked
 * never decides (403 `four_eyes`). Approving executes the refund through the existing path and is idempotent: send an
 * Idempotency-Key, and a repeated approval answers the same result without running again. Audited on the order.
 */
#[Group('Admin')]
class AdminRefundRequestController extends AdminController
{
    private const REQUEST = 'array{id: int, uuid: string, amount: string, status: string, status_label: string, reason: string, requested_by: array{id: int, name: string}|null, requested_at: string|null, decided_by: array{id: int, name: string}|null, decided_at: string|null, decision_note: string|null, execution: array<string, mixed>|null, mine: bool, can_decide: bool}';

    public function __construct(
        private readonly RefundRequestService $refunds,
        private readonly AdminOrderQuery $orders,
        private readonly AdminOrderPresenter $presenter,
    ) {}

    /**
     * Refund requests
     *
     * For the approval queue: `status` (pending — the default —, approved, rejected, all), `store_id`; newest first,
     * cursor pages (`per_page` 1–100, default 30). Each with its order (uuid, short code, store, status, total).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array<string, mixed>>, next_cursor: string|null, per_page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $status = $data['status'] ?? 'pending';

        $page = OrderRefundRequest::query()
            ->with(['order:id,uuid,short_code,store_id,status,total_price,payment_method', 'store:id,name', 'requester:id,name', 'decider:id,name'])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when(isset($data['store_id']), fn (Builder $query) => $query->where('store_id', (int) $data['store_id']))
            ->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        $member = $this->member($request);

        return $this->success([
            'data' => collect($page->items())->map(fn (OrderRefundRequest $refund) => $this->presenter->refundRequest($refund, $member) + [
                'order' => $refund->order ? [
                    'uuid' => (string) $refund->order->uuid,
                    'short_code' => $refund->order->short_code,
                    'status' => $refund->order->status->value,
                    'status_label' => $refund->order->status->label(),
                    'total' => $refund->order->total_price,
                    'payment_method' => $refund->order->payment_method,
                ] : null,
                'store' => $refund->store ? ['id' => (int) $refund->store->id, 'name' => (string) $refund->store->name] : null,
            ])->values()->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
        ]);
    }

    /**
     * Ask for a refund
     *
     * `amount` (so'm, up to 2 decimals; more than 0 and at most the card's `refunds.refundable`: paid − refunded) and
     * `reason`. Only for a finished order (delivered, canceled, rejected, refunded: 422 `order_active` otherwise), one
     * pending request per order (409 `refund_request_pending`). The request waits for another member's approval.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::REQUEST . '}')]
    #[ApiDocResponse(422, 'Too much (amount_exceeds_refundable, data.refundable) or the order is still in progress (order_active)', type: 'array{success: false, message: string, code: string, data: array{reason: string, refundable?: string}}')]
    #[ApiDocResponse(409, 'Another request is pending (refund_request_pending)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function store(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $order = $this->orders->base()->where('orders.uuid', $uuid)->first();
        if (!$order instanceof Order) {
            return $this->refuse('Buyurtma topilmadi.', 'not_found', 404);
        }

        try {
            $refund = $this->refunds->request($order, $this->member($request), (string) $data['amount'], $data['reason']);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)
            ->changes([], ['refund_request_id' => (int) $refund->id, 'amount' => $refund->amount, 'status' => $refund->status])
            ->meta(['short_code' => $order->short_code]);

        return $this->success($this->presenter->refundRequest($refund, $this->member($request)), "So'rov yaratildi. Tasdiq kutilmoqda.", 201);
    }

    /**
     * Approve a refund request
     *
     * By a member other than the one who asked (403 `four_eyes`). Runs the refund: a delivered order refunded in full
     * with nothing of it given back before becomes `refunded` (commission and settlement reversed, keshbek back to the
     * balance; mode `order_refunded`); otherwise (a part, the rest after a part, a cancelled order) the amount is
     * spread over the payments still paid (`execution.allocations`): a keshbek share goes back to the customer's
     * balance at once, exactly that amount (mode `balance` when all of it did), any other share is a manual refund
     * (mode `manual`). `execution.manual_amount` is what the approver returns by hand in the provider's cabinet or in
     * cash (no provider refund API is called). The amount is checked again against what is refundable now (a payment
     * the provider refunded since counts in full): 422 `amount_exceeds_refundable`. `reason` required.
     * Idempotent: a repeat (or the same Idempotency-Key) answers the same result; a request already decided otherwise
     * is 409 `refund_request_decided`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::REQUEST . '}')]
    #[ApiDocResponse(403, 'The requester cannot decide (four_eyes) or step-up needed (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'Already decided (refund_request_decided)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, true);
    }

    /**
     * Reject a refund request
     *
     * By a member other than the one who asked (403 `four_eyes`); `reason` (why) is required. A repeat answers the
     * same result.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::REQUEST . '}')]
    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, false);
    }

    private function decide(Request $request, int $id, bool $approve): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $refund = OrderRefundRequest::find($id);
        if ($refund === null) {
            return $this->refuse("So'rov topilmadi.", 'not_found', 404);
        }

        $member = $this->member($request);
        try {
            $result = $approve
                ? $this->refunds->approve($refund, $member, $data['reason'])
                : $this->refunds->reject($refund, $member, $data['reason']);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $refund = $result['request'];
        $audit = AdminAudit::for($request)->subject($refund->order)->store((int) $refund->store_id);
        if ($result['replayed']) {
            $audit->skip();
        } else {
            $audit->changes(['refund_request_status' => OrderRefundRequest::PENDING], ['refund_request_status' => $refund->status])->meta(array_filter([
                'refund_request_id' => (int) $refund->id,
                'amount' => $refund->amount,
                'requested_by' => $refund->requested_by !== null ? (int) $refund->requested_by : null,
                'execution' => $refund->execution,
            ], fn ($value) => $value !== null));
        }

        $message = match (true) {
            !$approve => "So'rov rad etildi.",
            ($refund->execution['manual_amount'] ?? '0.00') !== '0.00' => 'Tasdiqlandi. ' . RefundRequestService::uzs(Money::of($refund->execution['manual_amount'])) . "ni mijozga to'lov tizimi kabinetida yoki naqd qaytaring.",
            default => 'Tasdiqlandi, pul qaytarildi.',
        };

        return $this->success($this->presenter->refundRequest($refund, $member), $message);
    }
}
