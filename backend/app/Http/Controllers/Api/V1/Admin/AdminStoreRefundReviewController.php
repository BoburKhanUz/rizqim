<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Order;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Orders\AdminOrderException;
use App\Services\Admin\Orders\AdminOrderPresenter;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderSlaSettings;
use App\Services\Admin\Orders\StoreRefundReviewDecision;
use App\Services\Payment\StoreRefundReview;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wave P: a provider refund made on the store's own cashbox of a delivered order (a Payme CancelTransaction signed
 * with the store's own key) is shown as refunded but keeps RIZQIM's commission until a manager decides it here
 * (admin.orders.refund_approve, a step-up and a `reason`; audited: the decision, the payment ids and the amount).
 * Approving takes two different members (proposal, then confirmation), dismissing one. The card's `store_refund_review`
 * (latest), `store_refund_reviews` (all, newest first), `actions.can_decide_store_refund` and
 * `actions.can_approve_store_refund` show it; Bugun → "Diqqat talab qiladi" lists it (`store_refund_review`).
 */
#[Group('Admin')]
class AdminStoreRefundReviewController extends AdminController
{
    public function __construct(
        private readonly StoreRefundReviewDecision $decisions,
        private readonly AdminOrderQuery $orders,
        private readonly AdminOrderPresenter $presenter,
        private readonly OrderSlaSettings $sla,
    ) {}

    /**
     * Approve a store's provider refund
     *
     * Two members (four eyes, as refund requests): the money did go back to the customer (checked in the store's Payme
     * cabinet). The first member's approval is a proposal: the review keeps waiting (`store_refund_review.status`
     * `pending`, `proposed_by` set) and nothing is booked. A DIFFERENT member's approval refunds the order through
     * RIZQIM's own refund path — delivered → refunded, the commission and anything delivery booked reversed once,
     * nothing flagged for a manual refund (the provider already returned it). The proposer approving again: 403
     * `four_eyes` (a superadmin too). `reason` is required. Answers the order card. 409 `no_store_refund_review` when
     * nothing waits (decided already).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(403, 'The proposer cannot confirm it (four_eyes) or step-up needed (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'Nothing waits for a decision (no_store_refund_review)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(404, 'No such order (not_found)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function approve(Request $request, string $uuid): JsonResponse
    {
        return $this->decide($request, $uuid, true);
    }

    /**
     * Dismiss a store's provider refund
     *
     * RIZQIM's commission stays (a cancel the store sent itself, or a refund it makes at its own cost): the order stays
     * delivered with its payment refunded, the ledger is not touched. One member is enough (it keeps the platform's
     * money), a proposal to approve waiting is dropped with it. `reason` is required. Answers the order card.
     * 409 `no_store_refund_review` when nothing waits (decided already).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'Nothing waits for a decision (no_store_refund_review)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(404, 'No such order (not_found)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function dismiss(Request $request, string $uuid): JsonResponse
    {
        return $this->decide($request, $uuid, false);
    }

    private function decide(Request $request, string $uuid, bool $approve): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $order = $this->orders->base()->where('orders.uuid', $uuid)->first();
        if (!$order instanceof Order) {
            return $this->refuse('Buyurtma topilmadi.', 'not_found', 404);
        }

        $before = ['status' => $order->status->value, 'payment_status' => $order->payment_status->value];
        try {
            $member = $this->member($request);
            $result = $approve
                ? $this->decisions->approve($order, $member, $data['reason'])
                : $this->decisions->dismiss($order, $member, $data['reason']);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }
        $order = $result['order'];

        // Ids and the amount only: the decision (proposed, approved, dismissed), the payments it covered
        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)
            ->changes($before, ['status' => $order->status->value, 'payment_status' => $order->payment_status->value])
            ->meta([
                'short_code' => $order->short_code,
                'decision' => $result['decision'],
                'payment_ids' => $result['payment_ids'],
                'amount' => $result['amount'],
            ]);

        return $this->success(
            $this->presenter->card($order->fresh() ?? $order, $this->member($request), $this->sla->limits()),
            match ($result['decision']) {
                StoreRefundReviewDecision::PROPOSED => "Qaytarish taklif qilindi: uni boshqa xodim tasdiqlashi kerak.",
                StoreRefundReview::APPROVED => "Qaytarish tasdiqlandi: buyurtma qaytarildi, komissiya bekor qilindi.",
                default => "Qaytarish rad etildi: komissiya saqlanadi.",
            },
        );
    }
}
