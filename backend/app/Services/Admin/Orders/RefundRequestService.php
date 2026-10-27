<?php

namespace App\Services\Admin\Orders;

use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * P4-14: refund requests with four-eyes approval. A member with admin.orders.refund_request asks for an amount (at most
 * what was paid and not given back yet) of a finished order (delivered, canceled, rejected or refunded: an order still
 * in progress is cancelled first); a different member with admin.orders.refund_approve approves or rejects it.
 *
 * Approval runs the existing refund path, never writing statuses itself:
 *  - a delivered order refunded in full goes delivered → refunded through OrderStatusService::refund (the store's
 *    commission and settlement are reversed, keshbek goes back to the customer's balance, a card or cash payment is
 *    flagged for a manual refund) — mode `order_refunded`;
 *  - anything else (a cancelled order whose money was flagged for a manual refund, a part of a delivered order) is
 *    recorded as a manual refund the approver makes in the provider's cabinet or in cash — mode `manual`. No provider
 *    refund API is called: the code base has none (Payme's refunds arrive as its CancelTransaction webhook, Click and
 *    Uzum have no refund call here), see docs/API.md "Buyurtmalar".
 * What is refundable: paid = payments paid or refunded; refunded = approved requests + payments refunded outside any
 * request (keshbek returned on cancel, a Payme cancel). Payments an approval covered carry its id in
 * payload.refund_request_ids, so a later provider refund of the same money is not counted twice.
 *
 * Approving twice is a no-op (the same answer), under a row lock; the HTTP layer also honours Idempotency-Key.
 */
class RefundRequestService
{
    /** Order statuses a refund can be asked for. */
    public const REFUNDABLE_STATUSES = [OrderStatus::DELIVERED, OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED];

    public function __construct(
        private readonly OrderStatusService $orders,
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * @return array{paid: Money, refunded: Money, refundable: Money}
     */
    public function balance(Order $order): array
    {
        $payments = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value])
            ->get(['id', 'amount', 'status', 'payload']);

        $paid = Money::sum(...$payments->pluck('amount')->all());
        $outside = Money::sum(...$payments
            ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::REFUNDED && empty($payment->payload['refund_request_ids'] ?? null))
            ->pluck('amount')->all());
        $approved = Money::sum(...OrderRefundRequest::query()
            ->where('order_id', $order->id)
            ->where('status', OrderRefundRequest::APPROVED)
            ->pluck('amount')->all());

        $refunded = $outside->plus($approved);

        return [
            'paid' => $paid,
            'refunded' => $refunded,
            'refundable' => Money::max(Money::zero(), $paid->minus($refunded)),
        ];
    }

    public function canRequest(Order $order): bool
    {
        return in_array($order->status, self::REFUNDABLE_STATUSES, true);
    }

    /**
     * @throws AdminOrderException
     */
    public function request(Order $order, User $requester, string $amount, string $reason): OrderRefundRequest
    {
        return DB::transaction(function () use ($order, $requester, $amount, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!$this->canRequest($order)) {
                throw new AdminOrderException(
                    "Pulni qaytarishni faqat yakunlangan buyurtma uchun so'rash mumkin: avval buyurtmani bekor qiling yoki yetkazilishini kuting.",
                    'order_active',
                );
            }

            if (OrderRefundRequest::where('order_id', $order->id)->where('status', OrderRefundRequest::PENDING)->exists()) {
                throw new AdminOrderException("Bu buyurtma bo'yicha qaytarish so'rovi allaqachon tasdiq kutmoqda.", 'refund_request_pending', 409);
            }

            $money = Money::of($amount);
            $refundable = $this->balance($order)['refundable'];
            if (!$money->isPositive() || $money->isGreaterThan($refundable)) {
                throw new AdminOrderException(
                    $refundable->isPositive()
                        ? 'Qaytariladigan summa ' . self::uzs($refundable) . " dan oshmasligi kerak."
                        : "Bu buyurtma bo'yicha qaytariladigan pul yo'q.",
                    'amount_exceeds_refundable',
                    422,
                    ['refundable' => $refundable->toString()],
                );
            }

            return OrderRefundRequest::create([
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'amount' => $money->toString(),
                'status' => OrderRefundRequest::PENDING,
                'reason' => mb_substr(trim($reason), 0, 500),
                'requested_by' => $requester->id,
            ]);
        });
    }

    /**
     * @return array{request: OrderRefundRequest, replayed: bool}
     *
     * @throws AdminOrderException
     */
    public function approve(OrderRefundRequest $refund, User $approver, ?string $note): array
    {
        return DB::transaction(function () use ($refund, $approver, $note) {
            $refund = $this->lockForDecision($refund, $approver, OrderRefundRequest::APPROVED);
            if (!$refund->isPending()) {
                return ['request' => $refund, 'replayed' => true];
            }

            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $amount = Money::of($refund->amount);
            $balance = $this->balance($order);
            if ($amount->isGreaterThan($balance['refundable'])) {
                throw new AdminOrderException(
                    "So'ralgan summa endi qaytariladigan puldan (" . self::uzs($balance['refundable']) . ') ko\'p: so\'rovni rad eting va yangisini yarating.',
                    'amount_exceeds_refundable',
                    422,
                    ['refundable' => $balance['refundable']->toString()],
                );
            }

            $statusBefore = $order->status;
            $paymentsBefore = Payment::where('order_id', $order->id)->pluck('status', 'id')->map(fn ($status) => $status instanceof PaymentStatus ? $status->value : (string) $status);
            $full = $amount->equals($balance['refundable']);
            $mode = 'manual';

            if ($order->status === OrderStatus::DELIVERED && $full) {
                try {
                    $this->orders->refund($order, $approver, "Boshqaruv markazi, qaytarish so'rovi #{$refund->id}: {$refund->reason}");
                } catch (OrderTransitionException $e) {
                    throw new AdminOrderException($e->getMessage(), 'refund_failed', $e->status === 403 ? 403 : 422);
                }
                $mode = 'order_refunded';
            }

            $order->refresh();

            // What went back by itself (keshbek to the balance) during this approval, and what is left to return by hand
            $payments = Payment::where('order_id', $order->id)
                ->whereIn('status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value])
                ->lockForUpdate()
                ->get();
            $automatic = Money::sum(...$payments
                ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::REFUNDED && ($paymentsBefore[$payment->id] ?? null) !== PaymentStatus::REFUNDED->value)
                ->pluck('amount')->all());
            $automatic = Money::min($automatic, $amount);
            $manual = $amount->minus($automatic);

            foreach ($payments as $payment) {
                $payload = $payment->payload ?? [];
                $payload['refund_request_ids'] = array_values(array_unique([...($payload['refund_request_ids'] ?? []), (int) $refund->id]));
                if ($manual->isPositive() && $payment->status === PaymentStatus::PAID) {
                    $payload['manual_refund_approved'] = [...($payload['manual_refund_approved'] ?? []), [
                        'refund_request_id' => (int) $refund->id,
                        'amount' => $manual->toString(),
                        'approved_by' => (int) $approver->id,
                        'approved_at' => now()->toIso8601String(),
                    ]];
                }
                $payment->update(['payload' => $payload]);
            }

            $refund->update([
                'status' => OrderRefundRequest::APPROVED,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
                'execution' => [
                    'mode' => $mode,
                    'order_status_before' => $statusBefore->value,
                    'order_status_after' => $order->status->value,
                    'payment_method' => $order->payment_method,
                    'automatic_amount' => $automatic->toString(),
                    'manual_amount' => $manual->toString(),
                    // Provider refunds are not automated in this code base: the money is returned by hand
                    'provider_refund' => $manual->isPositive() ? 'manual' : 'none',
                ],
            ]);

            return ['request' => $refund->refresh(), 'replayed' => false];
        });
    }

    /**
     * @return array{request: OrderRefundRequest, replayed: bool}
     *
     * @throws AdminOrderException
     */
    public function reject(OrderRefundRequest $refund, User $approver, string $note): array
    {
        return DB::transaction(function () use ($refund, $approver, $note) {
            $refund = $this->lockForDecision($refund, $approver, OrderRefundRequest::REJECTED);
            if (!$refund->isPending()) {
                return ['request' => $refund, 'replayed' => true];
            }

            $refund->update([
                'status' => OrderRefundRequest::REJECTED,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_note' => mb_substr(trim($note), 0, 500),
            ]);

            return ['request' => $refund->refresh(), 'replayed' => false];
        });
    }

    /**
     * Whether this member may decide this request (the permission, and not the one who asked).
     */
    public function canDecide(OrderRefundRequest $refund, User $member): bool
    {
        return $refund->isPending()
            && (int) $refund->requested_by !== (int) $member->id
            && $this->permissions->has($member, AdminPermission::ORDERS_REFUND_APPROVE);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            OrderRefundRequest::PENDING => 'Tasdiq kutilmoqda',
            OrderRefundRequest::APPROVED => 'Tasdiqlandi',
            OrderRefundRequest::REJECTED => 'Rad etildi',
            default => $status,
        };
    }

    public static function uzs(Money $amount): string
    {
        return number_format($amount->toFloat(), 0, '.', ' ') . " so'm";
    }

    /**
     * The request under a row lock. Four eyes: the requester never decides. A request already decided the same way by
     * the same member is returned as it is (a retried approval); decided otherwise, it is a conflict.
     *
     * @throws AdminOrderException
     */
    private function lockForDecision(OrderRefundRequest $refund, User $approver, string $decision): OrderRefundRequest
    {
        $refund = OrderRefundRequest::whereKey($refund->id)->lockForUpdate()->firstOrFail();

        if ((int) $refund->requested_by === (int) $approver->id) {
            throw new AdminOrderException("O'zingiz yaratgan so'rovni tasdiqlay yoki rad eta olmaysiz: buni boshqa xodim qiladi.", 'four_eyes', 403);
        }

        if (!$refund->isPending() && !($refund->status === $decision && (int) $refund->decided_by === (int) $approver->id)) {
            throw new AdminOrderException("So'rov allaqachon ko'rib chiqilgan: " . mb_strtolower(self::statusLabel($refund->status)) . '.', 'refund_request_decided', 409);
        }

        return $refund;
    }
}
