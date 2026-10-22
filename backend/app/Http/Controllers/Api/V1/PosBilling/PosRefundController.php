<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\BillPresenter;
use App\Domain\Pos\Billing\RefundService;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P3-18: refunds within the shift (POS_SPEC 2.10).
 */
#[Group('POS: billing', "Kassa: schyot, bo'lish, to'lov, qaytarish, smena, X/Z va menejer tasdig'i (P3-18).")]
#[HeaderParameter('Idempotency-Key', description: "Required on every change: a new UUID per action, the same one when retrying it (428 without).", type: 'string')]
class PosRefundController extends PosBillingController
{
    /**
     * Refund a payment within the shift
     *
     * Gives back `amount` of a cash, card or QR payment (`payment_uuid`) of a paid bill, taken in the open shift of
     * this terminal (or `shift_uuid`): 409 `refund_outside_shift` for another shift's payment, 422
     * `refund_tender_invalid` for a deposit or nasiya part, 422 `refund_exceeds_payment` beyond what is left of it,
     * 409 `bill_not_fiscalized` before the sale receipt is issued, 409 `not_enough_cash` beyond the drawer's cash.
     * `reason` is required. Needs pos.refund, or a manager's `approval_token` for it (403 `approval_required`). Cash
     * leaves the drawer; a REFUND receipt naming the sale receipt is asked for (`fiscal`); the owner is told in
     * Telegram.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . BillPresenter::REFUND . '}')]
    public function store(Request $request, RefundService $refunds, BillPresenter $presenter): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'payment_uuid' => 'required|uuid',
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'reason' => 'required|string|min:3|max:500',
            'approval_token' => 'nullable|string|max:100',
            'shift_uuid' => 'nullable|uuid',
        ]);

        $payment = Payment::where('uuid', $data['payment_uuid'])->first();
        $order = $payment?->order_id !== null ? Order::find($payment->order_id) : null;
        if ($payment === null || $order === null || !$this->scope->allows($user, Permission::ORDERS_VIEW, $order)) {
            throw new HttpResponseException($this->error("To'lov topilmadi yoki sizning filialingizga tegishli emas.", [], 404));
        }

        $refund = $refunds->refund(
            $user,
            $payment,
            Money::of((string) $data['amount']),
            trim((string) $data['reason']),
            $data['approval_token'] ?? null,
            $data['shift_uuid'] ?? null,
            $this->terminal($user),
            $this->applied($request),
        );

        return $this->success($presenter->refund($refund), 'Pul qaytarildi.', 201);
    }
}
