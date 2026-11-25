<?php

namespace App\Services\Admin\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\StoreOwnRefund;
use Illuminate\Support\Facades\DB;

/**
 * Review finding 5: "Ovqatxona pulni qaytardi" — one platform member (admin.orders.refund_approve) records that the
 * store gave the customer back, from its own provider account, money RIZQIM never held (StoreOwnRefund), for providers
 * that send no refund confirmation (Click, Uzum, Paynet; Paylov once its cancel failed for good). It marks each such
 * payment done by hand (payload refunded_manually_by / _at / _covers; the payment's own status stays what the provider
 * reported), which turns the customer's "the restaurant refunds it" into "refunded" and clears the instruction on the
 * admin card and the partner order detail. It never touches the ledger or the commission: those were decided by the
 * two-person flow that approved the refund. Idempotent; refused for platform-collected money and for an order whose
 * refund was not decided. There is no store-side action: a store must not be able to tell the customer it refunded.
 */
final class StoreOwnRefundReturned
{
    /**
     * @return array{payment_ids: list<int>, replayed: bool}
     *
     * @throws AdminOrderException
     */
    public function mark(Order $order, User $member): array
    {
        return DB::transaction(function () use ($order, $member) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payments = Payment::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
            $markable = StoreOwnRefund::markable($order, $payments);

            if ($markable['reason'] === 'already') {
                return ['payment_ids' => [], 'replayed' => true];
            }
            if ($markable['reason'] === 'not_store_own_refund') {
                throw new AdminOrderException(
                    "Bu buyurtma puli ovqatxonaning o'z hisobiga tushmagan: uni RIZQIM qaytaradi, ovqatxona emas.",
                    'not_store_own_refund',
                    422,
                );
            }
            if ($markable['reason'] !== null) {
                throw new AdminOrderException(
                    "Bu buyurtma uchun tasdiqlangan qaytarish yo'q: avval qaytarish so'rovi tasdiqlanishi kerak.",
                    'no_approved_refund',
                    422,
                );
            }

            $now = now()->toIso8601String();
            foreach ($markable['payments'] as $payment) {
                $payment->forceFill(['payload' => array_merge($payment->payload ?? [], [
                    StoreOwnRefund::MANUAL_BY => (int) $member->id,
                    StoreOwnRefund::MANUAL_AT => $now,
                    StoreOwnRefund::MANUAL_COVERS => count((array) ($payment->payload['manual_refund_approved'] ?? [])),
                ])])->save();
            }

            return ['payment_ids' => array_map(fn (Payment $payment) => (int) $payment->id, $markable['payments']), 'replayed' => false];
        });
    }
}
