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
 * Approval runs the existing refund path, never writing order statuses itself:
 *  - a delivered order refunded in full while nothing of it was given back yet goes delivered → refunded through
 *    OrderStatusService::refund (the store's commission and settlement are reversed, keshbek goes back to the
 *    customer's balance, a card or cash payment is flagged for a manual refund) — mode `order_refunded`;
 *  - anything else (a part of a delivered order, the rest after an earlier part, a cancelled order whose money was
 *    flagged for a manual refund) is spread over the payments still paid, oldest first: a keshbek payment's share goes
 *    back to the customer's balance at once (as OrderStatusService::refundKeshbek does it; once a keshbek payment is
 *    given back in full it is `refunded`), any other share is a manual refund the approver makes in the provider's
 *    cabinet or in cash — mode `balance` when all of it went to the balance, `manual` otherwise. No provider refund API
 *    is called: the code base has none (Payme's refunds arrive as its CancelTransaction webhook, Click and Uzum have
 *    no refund call here), see docs/API.md "Buyurtmalar".
 * Each approval records how it was spread (`execution.allocations`: payment, amount, kind). What is refundable
 * (balanceOf): paid = payments paid or refunded; refunded = the full amount of every refunded payment (keshbek
 * returned on cancel, a Payme cancel — whether or not an approval covered a part of it before) + the approved shares
 * on payments that are still paid. So a provider refund after a partial approval never leaves money to refund twice.
 *
 * Approving twice is a no-op (the same answer), under a row lock; the HTTP layer also honours Idempotency-Key.
 */
class RefundRequestService
{
    /** Order statuses a refund can be asked for. */
    public const REFUNDABLE_STATUSES = [OrderStatus::DELIVERED, OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED];

    /** A share of an approval that went back to the customer's keshbek balance. */
    public const KIND_BALANCE = 'balance';

    /** A share the approver returns by hand (provider's cabinet or cash). */
    public const KIND_MANUAL = 'manual';

    public function __construct(
        private readonly OrderStatusService $orders,
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * @return array{paid: Money, refunded: Money, refundable: Money, allocated: array<int, Money>}
     */
    public function balance(Order $order): array
    {
        return self::balanceOf(
            Payment::query()->where('order_id', $order->id)->get(['id', 'provider', 'amount', 'status', 'payload']),
            self::approvedRequests([(int) $order->id])->get(),
        );
    }

    /**
     * Approved requests of these orders, with what balanceOf reads.
     *
     * @param  list<int>  $orderIds
     * @return \Illuminate\Database\Eloquent\Builder<OrderRefundRequest>
     */
    public static function approvedRequests(array $orderIds): \Illuminate\Database\Eloquent\Builder
    {
        return OrderRefundRequest::query()
            ->whereIn('order_id', $orderIds)
            ->where('status', OrderRefundRequest::APPROVED)
            ->orderBy('id')
            ->select(['id', 'order_id', 'amount', 'status', 'execution']);
    }

    /**
     * What was paid, given back and is left of one order: paid = payments paid or refunded; refunded = the full amount
     * of every refunded payment + the approved shares (execution.allocations) on payments still paid. A share on a
     * payment refunded since is covered by that payment's refund and not counted again. An approval without
     * allocations (written before they were recorded) counts in full, and so does a share on a payment that is neither
     * paid nor refunded: never less than what really went back. `allocated` — the approved shares per payment still
     * paid.
     *
     * @param  iterable<Payment>  $payments  the order's payments (any status)
     * @param  iterable<OrderRefundRequest>  $approved  the order's approved requests
     * @return array{paid: Money, refunded: Money, refundable: Money, allocated: array<int, Money>}
     */
    public static function balanceOf(iterable $payments, iterable $approved): array
    {
        $counted = [];
        foreach ($payments as $payment) {
            if (in_array($payment->status, [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)) {
                $counted[(int) $payment->id] = $payment;
            }
        }

        $paid = Money::zero();
        $refunded = Money::zero();
        foreach ($counted as $payment) {
            $paid = $paid->plus($payment->amount);
            if ($payment->status === PaymentStatus::REFUNDED) {
                $refunded = $refunded->plus($payment->amount);
            }
        }

        $allocated = [];
        foreach ($approved as $request) {
            $shares = self::allocations($request);
            if ($shares === null) {
                $refunded = $refunded->plus($request->amount);

                continue;
            }
            foreach ($shares as $share) {
                $payment = $counted[$share['payment_id']] ?? null;
                if ($payment?->status === PaymentStatus::REFUNDED) {
                    continue;
                }
                $refunded = $refunded->plus($share['amount']);
                if ($payment !== null) {
                    $allocated[$share['payment_id']] = ($allocated[$share['payment_id']] ?? Money::zero())->plus($share['amount']);
                }
            }
        }

        return [
            'paid' => $paid,
            'refunded' => $refunded,
            'refundable' => Money::max(Money::zero(), $paid->minus($refunded)),
            'allocated' => $allocated,
        ];
    }

    /**
     * How an approval was spread over the payments, or null when it was not recorded.
     *
     * @return list<array{payment_id: int, amount: Money}>|null
     */
    private static function allocations(OrderRefundRequest $request): ?array
    {
        $rows = $request->execution['allocations'] ?? null;
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $shares = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['payment_id'], $row['amount'])) {
                return null;
            }
            $shares[] = ['payment_id' => (int) $row['payment_id'], 'amount' => Money::of((string) $row['amount'])];
        }

        return $shares;
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
            $payments = Payment::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
            $balance = self::balanceOf($payments, self::approvedRequests([(int) $order->id])->get());
            if ($amount->isGreaterThan($balance['refundable'])) {
                throw new AdminOrderException(
                    "So'ralgan summa endi qaytariladigan puldan (" . self::uzs($balance['refundable']) . ') ko\'p: so\'rovni rad eting va yangisini yarating.',
                    'amount_exceeds_refundable',
                    422,
                    ['refundable' => $balance['refundable']->toString()],
                );
            }

            $statusBefore = $order->status;
            $now = now();

            // The whole order and nothing of it given back yet: the order's own refund path (ledger reversal included)
            if ($order->status === OrderStatus::DELIVERED && $balance['refunded']->isZero() && $amount->equals($balance['paid'])) {
                $paidBefore = $payments->filter(fn (Payment $payment) => $payment->status === PaymentStatus::PAID)->keyBy('id');
                try {
                    $this->orders->refund($order, $approver, "Boshqaruv markazi, qaytarish so'rovi #{$refund->id}: {$refund->reason}");
                } catch (OrderTransitionException $e) {
                    throw new AdminOrderException($e->getMessage(), 'refund_failed', $e->status === 403 ? 403 : 422);
                }
                $mode = 'order_refunded';

                // Every payment was paid (nothing refunded): keshbek went back to the balance by itself, the rest is manual
                $shares = [];
                foreach (Payment::whereKey($paidBefore->keys()->all())->orderBy('id')->lockForUpdate()->get() as $payment) {
                    $shares[] = [
                        'payment' => $payment,
                        'amount' => Money::of($payment->amount),
                        'kind' => $payment->status === PaymentStatus::REFUNDED ? self::KIND_BALANCE : self::KIND_MANUAL,
                    ];
                }
            } else {
                $shares = $this->spread($order, $payments, $balance['allocated'], $amount, $refund, $now);
                $mode = collect($shares)->every(fn (array $share) => $share['kind'] === self::KIND_BALANCE) ? 'balance' : 'manual';
            }

            $automatic = Money::sum(...array_map(fn (array $share) => $share['amount'], array_filter($shares, fn (array $share) => $share['kind'] === self::KIND_BALANCE)));
            $manual = $amount->minus($automatic);

            // Only the payments this approval touched carry it (never one refunded before it)
            foreach ($shares as $share) {
                $payment = $share['payment'];
                $payload = $payment->payload ?? [];
                $payload['refund_request_ids'] = array_values(array_unique([...($payload['refund_request_ids'] ?? []), (int) $refund->id]));
                if ($share['kind'] === self::KIND_MANUAL) {
                    $payload['manual_refund_approved'] = [...($payload['manual_refund_approved'] ?? []), [
                        'refund_request_id' => (int) $refund->id,
                        'amount' => $share['amount']->toString(),
                        'approved_by' => (int) $approver->id,
                        'approved_at' => $now->toIso8601String(),
                    ]];
                }
                $payment->update(['payload' => $payload]);
            }

            $order->refresh();
            $refund->update([
                'status' => OrderRefundRequest::APPROVED,
                'decided_by' => $approver->id,
                'decided_at' => $now,
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
                    'allocations' => array_map(fn (array $share) => [
                        'payment_id' => (int) $share['payment']->id,
                        'provider' => (string) $share['payment']->provider,
                        'amount' => $share['amount']->toString(),
                        'kind' => $share['kind'],
                    ], $shares),
                ],
            ]);

            return ['request' => $refund->refresh(), 'replayed' => false];
        });
    }

    /**
     * Spreads an amount over the payments still paid, oldest first, each up to what of it is not approved yet. A keshbek
     * share goes back to the customer's balance now, exactly that amount, as OrderStatusService::refundKeshbek does it
     * (the balance, then the payment: `refunded` once nothing of it is left, and the order's payment status with it
     * when no payment is paid any more, so no later refund path returns it again); any other share is for the approver
     * to return by hand.
     *
     * @param  \Illuminate\Support\Collection<int, Payment>  $payments  the order's payments, locked
     * @param  array<int, Money>  $allocated  approved shares per payment still paid (balanceOf)
     * @return list<array{payment: Payment, amount: Money, kind: string}>
     *
     * @throws AdminOrderException
     */
    private function spread(Order $order, \Illuminate\Support\Collection $payments, array $allocated, Money $amount, OrderRefundRequest $refund, \Carbon\CarbonInterface $now): array
    {
        $shares = [];
        $left = $amount;
        foreach ($payments as $payment) {
            if (!$left->isPositive()) {
                break;
            }
            if ($payment->status !== PaymentStatus::PAID) {
                continue;
            }
            $room = Money::of($payment->amount)->minus($allocated[(int) $payment->id] ?? Money::zero());
            if (!$room->isPositive()) {
                continue;
            }
            $share = Money::min($room, $left);
            $left = $left->minus($share);

            $toBalance = $payment->provider === 'keshbek';
            if ($toBalance) {
                // A numeric string keeps the amount exact; Laravel accepts it although the stub says float|int
                User::whereKey($order->user_id)->increment('balance', $share->toString()); // @phpstan-ignore argument.type

                $payload = $payment->payload ?? [];
                $payload['refunded_to_balance_parts'] = [...($payload['refunded_to_balance_parts'] ?? []), [
                    'refund_request_id' => (int) $refund->id,
                    'amount' => $share->toString(),
                    'refunded_at' => $now->toIso8601String(),
                ]];
                $update = ['payload' => $payload];
                if ($share->equals($room)) {
                    $update['status'] = PaymentStatus::REFUNDED;
                    $update['cancelled_at'] = $now;
                    $update['payload'] += [
                        'refunded_to_balance' => Money::of($payment->amount)->toFloat(),
                        'refunded_at' => $now->toIso8601String(),
                    ];
                }
                $payment->update($update);
            }

            $shares[] = ['payment' => $payment, 'amount' => $share, 'kind' => $toBalance ? self::KIND_BALANCE : self::KIND_MANUAL];
        }

        if ($left->isPositive()) {
            // Not reachable while amount <= refundable; never approve money no payment holds
            throw new AdminOrderException("So'ralgan summani to'lovlar qoplamaydi: so'rovni rad eting va yangisini yarating.", 'amount_exceeds_refundable', 422);
        }

        if ($order->payment_status === PaymentStatus::PAID && $payments->every(fn (Payment $payment) => $payment->status !== PaymentStatus::PAID)) {
            $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
        }

        return $shares;
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

    /**
     * "50 000 so'm"; with the tiyin when there are any ("50 000,50 so'm"), so a sum someone returns by hand is exact.
     */
    public static function uzs(Money $amount): string
    {
        $decimals = str_ends_with($amount->toString(), '.00') ? 0 : 2;

        return number_format($amount->toFloat(), $decimals, ',', ' ') . " so'm";
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
