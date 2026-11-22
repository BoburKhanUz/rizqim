<?php

namespace App\Services\Admin\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Payment\StoreRefundReview;
use Illuminate\Support\Facades\DB;

/**
 * Wave P: a manager's decision on a provider refund held by StoreRefundReview (a refund on the store's own cashbox of
 * an order RIZQIM settled). Approve: the provider did give the money back (checked in the store's cabinet or with the
 * provider), so the order goes delivered → refunded through RIZQIM's own refund path (OrderStatusService::refund with
 * refunded_by_provider: the commission and anything delivery booked reversed once, nothing flagged for a manual
 * refund). Dismiss: the commission stays (a forged cancel, or a refund the store makes at its own cost); the order
 * stays delivered with its payment refunded. Each review is decided once, under the order's lock; a second decision
 * is a 409 `no_store_refund_review`.
 */
final class StoreRefundReviewDecision
{
    public function __construct(private readonly OrderStatusService $orders) {}

    /**
     * @throws AdminOrderException
     */
    public function approve(Order $order, User $member, string $reason): Order
    {
        return $this->decide($order, $member, $reason, StoreRefundReview::APPROVED);
    }

    /**
     * @throws AdminOrderException
     */
    public function dismiss(Order $order, User $member, string $reason): Order
    {
        return $this->decide($order, $member, $reason, StoreRefundReview::DISMISSED);
    }

    private function decide(Order $order, User $member, string $reason, string $decision): Order
    {
        return DB::transaction(function () use ($order, $member, $reason, $decision) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payments = Payment::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (Payment $payment) => (StoreRefundReview::of($payment)['status'] ?? null) === StoreRefundReview::PENDING);

            if ($order->store_refund_review_at === null || $payments->isEmpty()) {
                throw new AdminOrderException("Bu buyurtma bo'yicha tekshiruv kutayotgan qaytarish yo'q.", 'no_store_refund_review', 409);
            }

            $note = mb_substr(trim($reason), 0, 500);
            if ($decision === StoreRefundReview::APPROVED) {
                $providers = $payments->map(fn (Payment $payment) => ucfirst((string) (StoreRefundReview::of($payment)['provider'] ?? $payment->provider)))->unique()->implode(', ');
                try {
                    $this->orders->refund($order, $member, "Boshqaruv markazi: ovqatxona kassasi orqali qilingan {$providers} qaytarishi tasdiqlandi. {$note}", true);
                } catch (OrderTransitionException $e) {
                    throw new AdminOrderException($e->getMessage(), 'refund_failed', $e->status === 403 ? 403 : 422);
                }
            }

            foreach ($payments as $payment) {
                $payment->update(['payload' => array_merge($payment->payload ?? [], [
                    StoreRefundReview::KEY => array_merge(StoreRefundReview::of($payment) ?? [], [
                        'status' => $decision,
                        'decided_by' => (int) $member->id,
                        'decided_at' => now()->toIso8601String(),
                        'note' => $note,
                    ]),
                ])]);
            }

            $order->refresh()->forceFill(['store_refund_review_at' => null])->save();

            return $order;
        });
    }
}
