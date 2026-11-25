<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Order;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Orders\AdminOrderException;
use App\Services\Admin\Orders\AdminOrderPresenter;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderSlaSettings;
use App\Services\Admin\Orders\StoreOwnRefundReturned;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Review finding 5 (owner decision 4): the store gave back, from its own provider account, money RIZQIM never held
 * (`refund_by: store_own_account`), and the provider sends no refund confirmation (Click, Uzum, Paynet; Paylov once its
 * cancel failed for good). One member with admin.orders.refund_approve records it (a step-up and a `reason`; audited
 * with the actor, the time and the payment ids): StoreOwnRefundReturned.
 */
#[Group('Admin')]
class AdminStoreOwnRefundController extends AdminController
{
    public function __construct(
        private readonly StoreOwnRefundReturned $returned,
        private readonly AdminOrderQuery $orders,
        private readonly AdminOrderPresenter $presenter,
        private readonly OrderSlaSettings $sla,
    ) {}

    /**
     * Mark a store's own refund as done
     *
     * "Ovqatxona pulni qaytardi": the store returned the customer's money from its own Click / Payme / Uzum / Paynet /
     * Paylov account (checked with the store: a receipt, the store's cabinet). Each such payment of the order whose refund
     * was decided (an approved refund request's share, or a refund flagged by the order's cancel or refund) is marked done
     * by hand (`payments[].refunded_manually`), never as the provider's confirmation (its `status` stays). The customer's
     * order then reads refunded (`refund.returned` true), the admin card and the partner order detail
     * `refund_returned: true`. The ledger and the commission are not touched: the two-person flow decided them. One
     * member is enough; marking again changes nothing (200). 422 `not_store_own_refund` for money RIZQIM collected (it
     * refunds that itself), 422 `no_approved_refund` when no refund of the store's money was decided yet. `reason` is
     * required. Answers the order card. Stores have no such action.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Not the store\'s money (not_store_own_refund) or no refund decided (no_approved_refund)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(404, 'No such order (not_found)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function returned(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $order = $this->orders->base()->where('orders.uuid', $uuid)->first();
        if (!$order instanceof Order) {
            return $this->refuse('Buyurtma topilmadi.', 'not_found', 404);
        }

        $member = $this->member($request);
        try {
            $result = $this->returned->mark($order, $member);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        // The actor and the time are the audit row's own; the reason is added from the request (RecordAdminAudit)
        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)->meta([
            'short_code' => $order->short_code,
            'payment_ids' => $result['payment_ids'],
            'refund_by' => \App\Services\Payment\StoreOwnRefund::REFUND_BY,
            'replayed' => $result['replayed'],
        ]);

        return $this->success(
            $this->presenter->card($order->fresh() ?? $order, $member, $this->sla->limits()),
            $result['replayed'] ? "Allaqachon belgilangan: ovqatxona pulni qaytargan." : "Belgilandi: ovqatxona pulni mijozga qaytardi.",
        );
    }
}
