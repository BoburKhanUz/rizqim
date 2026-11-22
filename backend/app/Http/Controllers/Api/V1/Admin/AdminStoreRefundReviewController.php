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
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wave P: a provider refund made on the store's own cashbox of a delivered order (a Payme CancelTransaction signed
 * with the store's own key) is shown as refunded but keeps RIZQIM's commission until a manager decides it here
 * (admin.orders.refund_approve, a step-up and a `reason`; audited). The card's `store_refund_review` and
 * `actions.can_decide_store_refund` show it; Bugun → "Diqqat talab qiladi" lists it (`store_refund_review`).
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
     * The money did go back to the customer (checked in the store's Payme cabinet): the order is refunded through
     * RIZQIM's own refund path — delivered → refunded, the commission and anything delivery booked reversed once,
     * nothing flagged for a manual refund (the provider already returned it). `reason` is required. Answers the order
     * card. 409 `no_store_refund_review` when nothing waits (decided already).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
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
     * delivered with its payment refunded, the ledger is not touched. `reason` is required. Answers the order card.
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
            $order = $approve
                ? $this->decisions->approve($order, $member, $data['reason'])
                : $this->decisions->dismiss($order, $member, $data['reason']);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)
            ->changes($before, ['status' => $order->status->value, 'payment_status' => $order->payment_status->value])
            ->meta(['short_code' => $order->short_code, 'decision' => $approve ? 'approved' : 'dismissed']);

        return $this->success(
            $this->presenter->card($order->fresh() ?? $order, $this->member($request), $this->sla->limits()),
            $approve ? "Qaytarish tasdiqlandi: buyurtma qaytarildi, komissiya bekor qilindi." : "Qaytarish rad etildi: komissiya saqlanadi.",
        );
    }
}
