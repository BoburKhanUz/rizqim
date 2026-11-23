<?php

namespace App\Services\Admin\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Payment\StoreRefundReview;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wave P: a manager's decision on a provider refund held by StoreRefundReview (a refund on the store's own cashbox of
 * an order RIZQIM settled).
 *
 * Approve takes two members (four eyes, as P4-14 refund requests: RefundRequestService, the asker never decides). The
 * store raises the review at will with its own key, and approving reverses platform money, so one member's word is
 * never enough: the first approval is a proposal (`proposed_by`, `proposed_at`, `proposal_note` on the review; the
 * review keeps waiting, nothing is booked), and a DIFFERENT member's approval runs it — the order goes delivered →
 * refunded through RIZQIM's own refund path (OrderStatusService::refund with refunded_by_provider: the commission and
 * anything delivery booked reversed once, nothing flagged for a manual refund). The proposer approving again is a 403
 * `four_eyes` (a superadmin too).
 *
 * Dismiss stays one member: it keeps the platform's money (a forged cancel, or a refund the store makes at its own
 * cost); the order stays delivered with its payment refunded. A proposal waiting is dropped with it.
 *
 * Each review is decided once, under the order's lock; a decision on nothing waiting is a 409 `no_store_refund_review`.
 */
final class StoreRefundReviewDecision
{
    public const PROPOSED = 'proposed';

    public function __construct(private readonly OrderStatusService $orders) {}

    /**
     * The first member proposes, a different one runs it.
     *
     * @return array{order: Order, decision: string, payment_ids: list<int>, amount: string}
     *
     * @throws AdminOrderException
     */
    public function approve(Order $order, User $member, string $reason): array
    {
        return $this->decide($order, $member, $reason, StoreRefundReview::APPROVED);
    }

    /**
     * @return array{order: Order, decision: string, payment_ids: list<int>, amount: string}
     *
     * @throws AdminOrderException
     */
    public function dismiss(Order $order, User $member, string $reason): array
    {
        return $this->decide($order, $member, $reason, StoreRefundReview::DISMISSED);
    }

    /**
     * @return array{order: Order, decision: string, payment_ids: list<int>, amount: string}
     */
    private function decide(Order $order, User $member, string $reason, string $decision): array
    {
        return DB::transaction(function () use ($order, $member, $reason, $decision) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var Collection<int, Payment> $payments */
            $payments = Payment::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (Payment $payment) => (StoreRefundReview::of($payment)['status'] ?? null) === StoreRefundReview::PENDING)
                ->values();

            if ($order->store_refund_review_at === null || $payments->isEmpty()) {
                throw new AdminOrderException("Bu buyurtma bo'yicha tekshiruv kutayotgan qaytarish yo'q.", 'no_store_refund_review', 409);
            }

            $note = mb_substr(trim($reason), 0, 500);
            $result = fn (string $done) => [
                'order' => $order,
                'decision' => $done,
                'payment_ids' => $payments->map(fn (Payment $payment) => (int) $payment->id)->all(),
                'amount' => Money::sum(...$payments->map(fn (Payment $payment) => Money::of($payment->amount))->all())->toString(),
            ];

            if ($decision === StoreRefundReview::APPROVED) {
                $proposer = self::proposer($payments);
                if ($proposer === null) {
                    $this->mark($payments, [
                        'proposed_by' => (int) $member->id,
                        'proposed_at' => now()->toIso8601String(),
                        'proposal_note' => $note,
                    ]);

                    return $result(self::PROPOSED);
                }
                if ($proposer === (int) $member->id) {
                    throw new AdminOrderException(
                        "Qaytarishni taklif qilgan xodim uni tasdiqlay olmaydi: ikkinchi tasdiqni boshqa xodim beradi.",
                        'four_eyes',
                        403,
                    );
                }

                $providers = $payments->map(fn (Payment $payment) => ucfirst((string) (StoreRefundReview::of($payment)['provider'] ?? $payment->provider)))->unique()->implode(', ');
                try {
                    $this->orders->refund($order, $member, "Boshqaruv markazi: ovqatxona kassasi orqali qilingan {$providers} qaytarishi tasdiqlandi. {$note}", true);
                } catch (OrderTransitionException $e) {
                    throw new AdminOrderException($e->getMessage(), 'refund_failed', $e->status === 403 ? 403 : 422);
                }
            }

            $this->mark($payments, [
                'status' => $decision,
                'decided_by' => (int) $member->id,
                'decided_at' => now()->toIso8601String(),
                'note' => $note,
            ]);

            $order->refresh()->forceFill(['store_refund_review_at' => null])->save();

            return $result($decision);
        });
    }

    /**
     * Who proposed approving these pending reviews, or null (one proposal covers all of an order's pending reviews).
     *
     * @param  Collection<int, Payment>  $payments
     */
    private static function proposer(Collection $payments): ?int
    {
        foreach ($payments as $payment) {
            $by = StoreRefundReview::of($payment)['proposed_by'] ?? null;
            if (is_numeric($by)) {
                return (int) $by;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  array<string, mixed>  $fields
     */
    private function mark(Collection $payments, array $fields): void
    {
        foreach ($payments as $payment) {
            $payment->update(['payload' => array_merge($payment->payload ?? [], [
                StoreRefundReview::KEY => array_merge(StoreRefundReview::of($payment) ?? [], $fields),
            ])]);
        }
    }
}
