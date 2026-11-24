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
 *    cabinet or in cash — mode `balance` when all of it went to the balance, `manual` otherwise. No Click/Payme refund
 *    API is called: the code base has none (Payme's refunds arrive as its CancelTransaction webhook, Click and Uzum have
 *    no refund call here), see docs/API.md "Buyurtmalar".
 * Wave P: Paylov refunds only a whole transaction (payment/cancel has no amount): a request for a Paylov order must be
 * for everything still refundable (`paylov_partial_refund_not_supported` otherwise), none while one is under way
 * (`paylov_refund_in_progress`); its share is kind `provider`, given back by RefundPaylovPayment after the commit.
 * P4-23: a part of a delivered order also reverses the same share of the store's ledger booking (commission,
 * settlement, debt payoff; LedgerService::reverseDeliveredOrderShare), recorded as `execution.ledger_reversal`. While
 * delivery's booking has failed (settlement_failed_at) there is nothing to take back yet: the part is marked
 * `execution.ledger_reversal_deferred` and the retry that books the order reverses it (SettlementRecoveryService).
 * Each approval records how it was spread (`execution.allocations`: payment, amount, kind). What is refundable
 * (balanceOf): paid = payments paid or refunded; refunded = the full amount of every refunded payment (keshbek
 * returned on cancel, a Payme cancel — whether or not an approval covered a part of it before) + the approved shares
 * on payments that are still paid. So a provider refund after a partial approval never leaves money to refund twice.
 * Security review F1: the ledger share is NOT that ratio (a payment paid and cancelled before delivery would count as
 * both paid and refunded): it is what approved requests gave back after delivery of what was paid at delivery
 * (deliveryShareOf), and the whole order through the order's own refund path when that is everything paid at delivery.
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

    /** Wave P: a share the provider gives back by itself (Paylov's payment/cancel, a full refund of the payment). */
    public const KIND_PROVIDER = 'provider';

    public function __construct(
        private readonly OrderStatusService $orders,
        private readonly AdminPermissionService $permissions,
        private readonly \App\Services\Finance\LedgerService $ledger,
    ) {}

    /**
     * @return array{paid: Money, refunded: Money, refundable: Money, allocated: array<int, Money>}
     */
    public function balance(Order $order): array
    {
        return self::balanceOf(
            Payment::query()->where('order_id', $order->id)->get(['id', 'provider', 'amount', 'status', 'payload', 'cancelled_at']),
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
     * Security review F1: the two amounts a part of a delivered order reverses its share of the ledger booking by
     * (LedgerService::reverseDeliveredOrderShare): `refunded` — what was given back after delivery, i.e. the approved
     * requests made while the order was delivered (+ $plus, the request being approved now); `paid` — what was paid when
     * the commission was booked: the payments still paid and those refunded after delivery. A payment refunded while the
     * order was still in progress (a Payme or Paylov transaction paid and cancelled before delivery, each leaving a
     * `refunded` payment) takes no part in either: the commission was booked on the money the order was finally paid
     * with. A provider refund after delivery that is not an approved request (StoreRefundReview's hold) counts in `paid`
     * only: RIZQIM's commission on it is decided by the review, never by a later part. balanceOf stays what may still
     * be refunded; this is only the ratio of the ledger reversal.
     *
     * @param  iterable<Payment>  $payments  the order's payments (any status)
     * @param  iterable<OrderRefundRequest>  $approved  the order's approved requests (those to count)
     * @return array{refunded: Money, paid: Money}
     */
    public static function deliveryShareOf(Order $order, iterable $payments, iterable $approved, ?Money $plus = null): array
    {
        $refunded = $plus ?? Money::zero();
        $touched = [];
        foreach ($approved as $request) {
            if (($request->execution['order_status_before'] ?? null) !== OrderStatus::DELIVERED->value) {
                continue;
            }
            $refunded = $refunded->plus($request->amount);
            foreach (self::allocations($request) ?? [] as $share) {
                $touched[$share['payment_id']] = true;
            }
        }

        $deliveredAt = false; // not read yet
        $paid = Money::zero();
        foreach ($payments as $payment) {
            if ($payment->status === PaymentStatus::PAID) {
                $paid = $paid->plus($payment->amount);

                continue;
            }
            if ($payment->status !== PaymentStatus::REFUNDED) {
                continue;
            }
            $payload = $payment->payload ?? [];
            if (($payload['refunded_before_delivery'] ?? false) === true) {
                continue;
            }
            if (($payload['refunded_after_delivery'] ?? false) !== true && !isset($touched[(int) $payment->id]) && $payment->cancelled_at !== null) {
                // Refunded before the marker was written: told apart by the time, against the order's first delivery
                if ($deliveredAt === false) {
                    $first = \App\Models\OrderStatusHistory::where('order_id', $order->id)->where('status', OrderStatus::DELIVERED->value)->min('created_at');
                    $deliveredAt = $first !== null ? \Illuminate\Support\Carbon::parse((string) $first) : null;
                }
                if ($deliveredAt !== null && $payment->cancelled_at->lt($deliveredAt)) {
                    continue;
                }
            }
            $paid = $paid->plus($payment->amount);
        }

        return ['refunded' => $refunded, 'paid' => $paid];
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
            $this->assertPaylovRefundable($order, $money, $refundable);
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
            $this->assertPaylovRefundable($order, $amount, $balance['refundable']);

            $statusBefore = $order->status;
            $now = now();
            $ledgerReversal = null;

            // F1: the ledger share counts only what was paid when delivery booked it and given back since
            $approvedBefore = self::approvedRequests([(int) $order->id])->get();
            $deliveryShare = self::deliveryShareOf($order, $payments, $approvedBefore);

            // The whole order and nothing of it given back since delivery: the order's own refund path (ledger reversal
            // included). Payments refunded before delivery are not part of it.
            if ($order->status === OrderStatus::DELIVERED && $deliveryShare['refunded']->isZero() && $amount->equals($deliveryShare['paid'])
                && $amount->equals($balance['refundable'])) {
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
                        'kind' => match (true) {
                            $payment->status === PaymentStatus::REFUNDED => self::KIND_BALANCE,
                            // Wave P: OrderStatusService::refund queued Paylov's payment/cancel for it
                            self::paylovRefundRequested($payment) => self::KIND_PROVIDER,
                            default => self::KIND_MANUAL,
                        },
                    ];
                }
            } else {
                $shares = $this->spread($order, $payments, $balance['allocated'], $amount, $refund, $now);
                $mode = collect($shares)->every(fn (array $share) => $share['kind'] === self::KIND_BALANCE) ? 'balance' : 'manual';

                // P4-23: a part of a delivered order takes back the same share of the store's commission and settlement
                // (pro-rata of what is given back in all; each request once, parts adding up to the whole reverse it all)
                if ($statusBefore === OrderStatus::DELIVERED) {
                    $share = self::deliveryShareOf($order, $payments, $approvedBefore, $amount);
                    $reversed = $this->ledger->reverseDeliveredOrderShare($order, (int) $refund->id, $share['refunded'], $share['paid'], (int) $approver->id);
                    $ledgerReversal = array_map(fn (Money $value) => $value->toString(), $reversed);
                }
            }

            $automatic = Money::sum(...array_map(fn (array $share) => $share['amount'], array_filter($shares, fn (array $share) => in_array($share['kind'], [self::KIND_BALANCE, self::KIND_PROVIDER], true))));
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
                    // Provider refunds are not automated for Click/Payme: the money is returned by hand; wave P: Paylov's
                    // are (payment/cancel, the whole payment)
                    'provider_refund' => match (true) {
                        $manual->isPositive() => 'manual',
                        collect($shares)->contains(fn (array $share) => $share['kind'] === self::KIND_PROVIDER) => 'paylov',
                        default => 'none',
                    },
                    // P4-23: what this part took back from the store (null: the order's own refund path did it all)
                    'ledger_reversal' => $ledgerReversal ?? null,
                    // Review W2: delivery's booking had failed, nothing was on the ledger to take back yet; the retry
                    // that books the order reverses this part then (SettlementRecoveryService)
                    'ledger_reversal_deferred' => $statusBefore === OrderStatus::DELIVERED && $order->settlement_failed_at !== null,
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
            // Wave P: a Paylov payment goes back whole through Paylov (assertPaylovRefundable refused any part of it)
            if ($payment->provider === 'paylov' && $payment->provider_transaction_id !== null && $share->equals(Money::of($payment->amount))) {
                $payload = $payment->payload ?? [];
                $payload['paylov']['refund'] = [
                    'status' => \App\Services\Payment\Paylov\PaylovPayments::REFUND_REQUESTED,
                    'reason' => "refund_request_{$refund->id}",
                    'requested_at' => $now->toIso8601String(),
                ];
                $payment->update(['payload' => $payload]);
                \App\Jobs\RefundPaylovPayment::dispatch((int) $payment->id);
                $shares[] = ['payment' => $payment, 'amount' => $share, 'kind' => self::KIND_PROVIDER];

                continue;
            }
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
     * Wave P: Paylov only refunds a whole transaction (developer.paylov.uz/subscribe/cancel-payment takes no amount), so
     * a refund request for a Paylov order is for everything still refundable, and none while a refund through Paylov is
     * still under way (it would be given back twice).
     *
     * @throws AdminOrderException
     */
    private function assertPaylovRefundable(Order $order, Money $amount, Money $refundable): void
    {
        $paylov = Payment::where('order_id', $order->id)->where('provider', 'paylov')->whereNotNull('provider_transaction_id')
            ->where('status', PaymentStatus::PAID->value)->get();
        if ($order->payment_method !== 'paylov' && $paylov->isEmpty()) {
            return;
        }

        if ($paylov->contains(fn (Payment $payment) => self::paylovRefundRequested($payment))) {
            throw new AdminOrderException(
                "Bu buyurtma puli Paylov orqali mijozga qaytarilmoqda: natijasini kuting, so'ng kerak bo'lsa yangi so'rov yarating.",
                'paylov_refund_in_progress',
                422,
            );
        }

        if (!$amount->equals($refundable)) {
            throw new AdminOrderException(
                "Paylov faqat to'liq qaytarishni qo'llaydi: bu buyurtma bo'yicha faqat butun summani (" . self::uzs($refundable) . ") qaytarish mumkin. Qisman qaytarish kerak bo'lsa, pulni Paylov biznes panelidan yoki boshqa yo'l bilan qo'lda qaytaring.",
                'paylov_partial_refund_not_supported',
                422,
                ['refundable' => $refundable->toString()],
            );
        }
    }

    private static function paylovRefundRequested(Payment $payment): bool
    {
        $refund = $payment->payload['paylov']['refund'] ?? null;

        return $payment->provider === 'paylov' && is_array($refund)
            && in_array($refund['status'] ?? null, [\App\Services\Payment\Paylov\PaylovPayments::REFUND_REQUESTED, \App\Services\Payment\Paylov\PaylovPayments::REFUND_RETRYING], true);
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
