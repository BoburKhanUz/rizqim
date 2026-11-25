<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * Owner decision 4: money paid into the store's own provider account (payment_owner STORE, OrderMerchantAccount) is
 * the store's to give back: RIZQIM holds none of it and has no way to send it back — Click, Payme, Uzum and Paynet
 * refunds are made in the store's own cabinet, and a Paylov payment is given back by RIZQIM's payment/cancel with the
 * store's account only while that works (once it failed for good, `manual_refund_required`, the store refunds it in its
 * Paylov business panel). Such a refund is labelled with the one machine code `refund_by: store_own_account` (Uzbek
 * text LABEL) on the admin order card and its payments, on refund requests, on the partner order detail and on the
 * customer's own order.
 *
 * The label never changes the money: commission on a delivered store-owned order is reversed only after a two-person
 * check (OrderStatusService::refund, RefundRequestService, StoreRefundReviewDecision).
 *
 * Review finding 5: such a refund is `returned` (done: "Ovqatxona o'z hisobidan qaytardi") once every such payment is
 * seen back — the provider's cancel arrived (refunded / cancelled), or, for providers that never confirm a refund
 * (Click, Uzum, Paynet; Paylov after its cancel failed for good), a platform member marked it done by hand
 * (markReturned: payload `refunded_manually_by`, never the provider's status).
 */
final class StoreOwnRefund
{
    public const REFUND_BY = 'store_own_account';

    public const LABEL = "Pulni ovqatxona o'z hisobidan qaytaradi";

    /** Review finding 5: the done state of LABEL. */
    public const RETURNED_LABEL = "Pulni ovqatxona o'z hisobidan qaytardi";

    /** Payload keys of a refund a platform member marked done by hand (markReturned). */
    public const MANUAL_BY = 'refunded_manually_by';

    public const MANUAL_AT = 'refunded_manually_at';

    /** How many approved manual shares (manual_refund_approved) the mark covered: a later approved share is not done yet. */
    public const MANUAL_COVERS = 'refunded_manually_covers';

    /**
     * Whether the order is "store-owned" in the sense of owner decision 4: paid online straight into the store's own
     * provider account (payment_owner STORE, OrderMerchantAccount), not cash. A cash order (the store's till) and a POS
     * order (cash, commission 0) are not: their refunds are unchanged. OrderStatusService refuses a person's refund of
     * such an order once delivered unless it went through a two-person check.
     */
    public static function isStoreOwned(Order $order): bool
    {
        return OrderMerchantAccount::isStore($order) && $order->payment_method !== 'cash';
    }

    /**
     * Whether giving this payment's money back is the store's, never RIZQIM's: a provider payment of a store-owned
     * order (keshbek goes back to the balance and cash is in the store's till anyway) that RIZQIM cannot refund itself.
     * A refund the store already made in its own cabinet (StoreRefundReview held it) is the store's too.
     */
    public static function isStores(Order $order, Payment $payment): bool
    {
        if (!self::isStoreOwned($order) || in_array($payment->provider, ['keshbek', 'cash'], true)) {
            return false;
        }
        if (is_array($payment->payload[StoreRefundReview::KEY] ?? null)) {
            return true;
        }
        if ($payment->provider === 'paylov' && $payment->provider_transaction_id !== null) {
            // RIZQIM's payment/cancel (with the store's account) gives it back, unless it was refused for good
            return !empty($payment->payload['manual_refund_required']);
        }

        return true;
    }

    /**
     * Whether a refund of this payment was recorded: flagged for someone to make by hand by a cancel or a refund
     * (manual_refund_required), a share of an approved refund request (manual_refund_approved), or a refund the store
     * made in its own cabinet that waits for (or got) a manager's decision (StoreRefundReview).
     */
    public static function refundRecorded(Payment $payment): bool
    {
        return !empty($payment->payload['manual_refund_required']) || !empty($payment->payload['manual_refund_approved'])
            || is_array($payment->payload[StoreRefundReview::KEY] ?? null);
    }

    /**
     * The order's `refund_by`: store_own_account once a refund of money RIZQIM cannot move was recorded, else null.
     *
     * @param  iterable<Payment>  $payments  the order's payments
     */
    public static function of(Order $order, iterable $payments): ?string
    {
        foreach ($payments as $payment) {
            if (self::refundRecorded($payment) && self::isStores($order, $payment)) {
                return self::REFUND_BY;
            }
        }

        return null;
    }

    /**
     * Whether this payment's money is seen back: the provider's cancel arrived (refunded or cancelled), or a platform
     * member marked the store's refund done by hand and no approved share came after that mark.
     */
    public static function returned(Payment $payment): bool
    {
        if (in_array($payment->status, [PaymentStatus::REFUNDED, PaymentStatus::CANCELLED], true)) {
            return true;
        }

        return self::markedReturned($payment);
    }

    /** Whether a platform member marked this payment's store refund done by hand, covering every approved share. */
    public static function markedReturned(Payment $payment): bool
    {
        return is_numeric($payment->payload[self::MANUAL_BY] ?? null)
            && (int) ($payment->payload[self::MANUAL_COVERS] ?? 0) >= count((array) ($payment->payload['manual_refund_approved'] ?? []));
    }

    /**
     * The order's store refunds recorded on payments the store must give back (refundRecorded and isStores).
     *
     * @param  iterable<Payment>  $payments  the order's payments
     * @return list<Payment>
     */
    public static function recorded(Order $order, iterable $payments): array
    {
        $recorded = [];
        foreach ($payments as $payment) {
            if (self::refundRecorded($payment) && self::isStores($order, $payment)) {
                $recorded[] = $payment;
            }
        }

        return $recorded;
    }

    /**
     * Whether the order's store refund is done (every recorded payment returned); null when there is none. The admin
     * card and the partner order detail show RETURNED_LABEL then, never the instruction to refund (a store that
     * already refunded must not be told to refund again).
     *
     * @param  iterable<Payment>  $payments  the order's payments
     */
    public static function returnedOf(Order $order, iterable $payments): ?bool
    {
        $recorded = self::recorded($order, $payments);

        return $recorded === [] ? null : collect($recorded)->every(fn (Payment $payment) => self::returned($payment));
    }

    /**
     * What the customer sees of such a refund (GET /orders, their own orders): `returned` once every such payment is
     * seen back (returned()), until then false ("the restaurant refunds it"). Null when there is no such refund.
     *
     * @param  iterable<Payment>  $payments  the order's payments
     * @return array{refund_by: string, returned: bool}|null
     */
    public static function forCustomer(Order $order, iterable $payments): ?array
    {
        $returned = self::returnedOf($order, $payments);

        return $returned === null ? null : ['refund_by' => self::REFUND_BY, 'returned' => $returned];
    }

    /**
     * The payments a platform member may mark as given back by the store by hand (markReturned): the store's own,
     * whose refund was decided — a share of an approved refund request (manual_refund_approved), or a refund flagged
     * by a cancel or a refund of the order (manual_refund_required on a cancelled, rejected or refunded order; Paylov's
     * cancel that failed for good) — and not seen back yet. `reason`: null (some to mark), `already` (all done),
     * `not_store_own_refund` (RIZQIM collected it, or nothing of it is the store's to give back) or
     * `no_approved_refund` (no refund decided for it yet).
     *
     * @param  iterable<Payment>  $payments  the order's payments
     * @return array{reason: string|null, payments: list<Payment>}
     */
    public static function markable(Order $order, iterable $payments): array
    {
        if (!self::isStoreOwned($order)) {
            return ['reason' => 'not_store_own_refund', 'payments' => []];
        }
        $closed = in_array($order->status, [\App\Enums\OrderStatus::REFUNDED, \App\Enums\OrderStatus::CANCELED, \App\Enums\OrderStatus::REJECTED], true);
        $decided = array_values(array_filter(self::recorded($order, $payments), fn (Payment $payment) => !empty($payment->payload['manual_refund_approved'])
            || (!empty($payment->payload['manual_refund_required']) && ($closed || ($payment->payload['manual_refund_reason'] ?? null) === 'paylov_cancel_failed'))));
        if ($decided === []) {
            return ['reason' => 'no_approved_refund', 'payments' => []];
        }
        $open = array_values(array_filter($decided, fn (Payment $payment) => !self::returned($payment)));

        return ['reason' => $open === [] ? 'already' : null, 'payments' => $open];
    }

    /**
     * Sets `refund` (forCustomer) on each of a customer's own orders (GET /orders) as a serialized relation, nothing
     * written back. One query per 1 000 orders, only for orders paid into the store's own account.
     *
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     */
    public static function attachForCustomer(\Illuminate\Support\Collection $orders): void
    {
        foreach ($orders->values()->chunk(1000) as $chunk) {
            $ids = $chunk->filter(fn (Order $order) => OrderMerchantAccount::isStore($order))->map(fn (Order $order) => (int) $order->id)->values()->all();
            $payments = $ids === [] ? collect() : Payment::query()->whereIn('order_id', $ids)
                ->get(['id', 'order_id', 'provider', 'provider_transaction_id', 'status', 'payload'])->groupBy('order_id');
            foreach ($chunk as $order) {
                $refund = self::forCustomer($order, $payments->get($order->id, collect()));
                $order->setRelation('refund', $refund !== null ? collect($refund) : null);
            }
        }
    }

    /**
     * Whether the money of this order still paid would be the store's to give back (a refund request not decided yet).
     *
     * @param  iterable<Payment>  $payments  the order's payments
     */
    public static function wouldBe(Order $order, iterable $payments): bool
    {
        foreach ($payments as $payment) {
            if ($payment->status === PaymentStatus::PAID && self::isStores($order, $payment)) {
                return true;
            }
        }

        return false;
    }
}
