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
 */
final class StoreOwnRefund
{
    public const REFUND_BY = 'store_own_account';

    public const LABEL = "Pulni ovqatxona o'z hisobidan qaytaradi";

    /**
     * Whether giving this payment's money back is the store's, never RIZQIM's: a provider payment of a store-owned
     * order (keshbek goes back to the balance and cash is in the store's till anyway) that RIZQIM cannot refund itself.
     */
    public static function isStores(Order $order, Payment $payment): bool
    {
        if (!OrderMerchantAccount::isStore($order) || in_array($payment->provider, ['keshbek', 'cash'], true)) {
            return false;
        }
        if ($payment->provider === 'paylov' && $payment->provider_transaction_id !== null) {
            // RIZQIM's payment/cancel (with the store's account) gives it back, unless it was refused for good
            return !empty($payment->payload['manual_refund_required']);
        }

        return true;
    }

    /**
     * Whether a refund of this payment was recorded for someone to make by hand: flagged by a cancel or a refund
     * (manual_refund_required), or a share of an approved refund request (manual_refund_approved).
     */
    public static function refundRecorded(Payment $payment): bool
    {
        return !empty($payment->payload['manual_refund_required']) || !empty($payment->payload['manual_refund_approved']);
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
     * What the customer sees of such a refund (GET /orders, their own orders): `returned` once every such payment is
     * seen back (the provider's cancel arrived: the payment is refunded or cancelled), until then false ("the
     * restaurant refunds it"). Null when there is no such refund.
     *
     * @param  iterable<Payment>  $payments  the order's payments
     * @return array{refund_by: string, returned: bool}|null
     */
    public static function forCustomer(Order $order, iterable $payments): ?array
    {
        $recorded = [];
        foreach ($payments as $payment) {
            if (self::refundRecorded($payment) && self::isStores($order, $payment)) {
                $recorded[] = $payment;
            }
        }
        if ($recorded === []) {
            return null;
        }

        $returned = collect($recorded)->every(fn (Payment $payment) => in_array($payment->status, [PaymentStatus::REFUNDED, PaymentStatus::CANCELLED], true));

        return ['refund_by' => self::REFUND_BY, 'returned' => $returned];
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
