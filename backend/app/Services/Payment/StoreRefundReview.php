<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wave P (forged provider cancels): a store that collects into its own merchant account knows the credential that
 * authenticates the provider's callbacks to RIZQIM (Payme's Merchant API is authenticated only by HTTP Basic
 * `Paycom:<cashbox key>`, the key the owner typed into POST /owner/settings), and Payme's Merchant API has no
 * merchant-side read-back of a transaction. So a CancelTransaction of a performed transaction on that cashbox may be
 * the store's own forgery as well as a real refund made in the cashbox's cabinet: RIZQIM cannot tell them apart.
 *
 * For an order RIZQIM has settled (delivered: the commission is booked or due), such a refund is therefore applied
 * only where the customer and the store see it — the payment is `refunded` and the order's payment status with it,
 * the order stays `delivered` (the food was handed over) — and the platform's money is kept: no commission reversal,
 * no ledger row. The refund is recorded as store-initiated (payment payload `store_refund_review`, orders
 * `store_refund_review_at`), audited, alerted (Log::critical, ids only) and listed in Bugun → "Diqqat talab qiladi";
 * managers decide it in the Boshqaruv markazi (App\Services\Admin\Orders\StoreRefundReviewDecision): approve — one
 * member proposes, a different one confirms (four eyes) — runs RIZQIM's own refund path (OrderStatusService::refund,
 * the commission reversed once), dismiss (one member) keeps the commission. The order is then never payable again
 * (moneyWentBack), so the store cannot raise the review once more by re-paying and cancelling.
 *
 * Owner decision 2: Paylov's verified transaction.cancel of a payment into the store's own Paylov account is held the
 * same way (PaylovPayments::applyCancel), unless RIZQIM itself asked Paylov for that refund (payment/cancel requested:
 * PaylovPayments::refundRequestedByRizqim). Paylov's cancel is read back from Paylov first, so the money did go back;
 * what stays the store's to answer for is whether RIZQIM's commission goes back with it. Its `reason` is null.
 *
 * A refund RIZQIM itself made first (the order is `refunded`: admin refund, approved refund request) is not held:
 * the provider's cancel then only gives the money back it was flagged for. Platform-owned orders keep their own rule
 * (their key is RIZQIM's: PaymeWebhookController refuses a cancel while the store holds the money).
 */
final class StoreRefundReview
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DISMISSED = 'dismissed';

    /** Payment payload key holding the review. */
    public const KEY = 'store_refund_review';

    /**
     * Whether a provider refund of this order's money must wait for a manager: the money went to the store's own
     * account and RIZQIM has settled the order (delivered). Read under the caller's lock of the order.
     */
    public static function applies(?Order $order): bool
    {
        return $order !== null && OrderMerchantAccount::isStore($order) && $order->status === OrderStatus::DELIVERED;
    }

    /**
     * Wave P review: a delivered order whose money was paid and given back (payment status `refunded`: a provider
     * refund held for review, dismissed or not, or a refund request that returned everything) never takes a payment
     * again — else a store re-pays it on its own cashbox and cancels again, raising a dismissed review once more. A
     * delivered order nothing was paid for yet (`pending`) still takes its late payment. Payme's accountError and
     * Click's isPayable read it.
     */
    public static function moneyWentBack(Order $order): bool
    {
        return $order->status === OrderStatus::DELIVERED && $order->payment_status === PaymentStatus::REFUNDED;
    }

    /**
     * Records the provider's refund of a paid payment without touching RIZQIM's ledger. The caller holds the order's
     * and the payment's locks inside its transaction and has already marked the payment `refunded`. Paylov's $report
     * (PaylovPayments::verifyCancel): the amounts its webhook and its read-back named (`amount_mismatch` when either is
     * not the payment's) and when the cancel was first reported (`reported_before_delivery`) go on the review.
     *
     * @param  array{reported_amount_tiyin?: int|null, paylov_amount_tiyin?: int|null, cancel_first_reported_at?: string|null}  $report
     */
    public function hold(Order $order, Payment $payment, string $provider, ?int $reason, array $report = []): void
    {
        $review = [
            'status' => self::PENDING,
            'provider' => $provider,
            'reason' => $reason,
            'reported_at' => now()->toIso8601String(),
        ];
        $mismatch = false;
        if ($provider === 'paylov') {
            // Review: Paylov's cancel names an amount (the webhook) and its read-back another; either one that is not the
            // payment's is shown to the manager (amount_mismatch). Approving still reverses what it always did.
            $tiyin = Money::of($payment->amount)->toTiyin();
            $reported = is_int($report['reported_amount_tiyin'] ?? null) ? $report['reported_amount_tiyin'] : null;
            $readBack = is_int($report['paylov_amount_tiyin'] ?? null) ? $report['paylov_amount_tiyin'] : null;
            $mismatch = ($reported !== null && $reported !== $tiyin) || ($readBack !== null && $readBack !== $tiyin);
            $first = is_string($report['cancel_first_reported_at'] ?? null) ? $report['cancel_first_reported_at'] : null;
            $review += [
                'reported_amount_tiyin' => $reported,
                'paylov_amount_tiyin' => $readBack,
                'amount_mismatch' => $mismatch,
                'cancel_first_reported_at' => $first,
                'reported_before_delivery' => $first !== null && self::reportedBeforeDelivery($order, $first),
            ];
        }

        $payment->update(['payload' => array_merge($payment->payload ?? [], [
            'refunded_after_delivery' => true,
            self::KEY => $review,
        ])]);

        $order->forceFill([
            'payment_status' => PaymentStatus::REFUNDED->value,
            'store_refund_review_at' => $order->store_refund_review_at ?? now(),
        ])->save();

        app(AuditLogger::class)->record(
            'order.store_refund_held',
            $order,
            ['payment_status' => PaymentStatus::PAID->value],
            ['payment_status' => PaymentStatus::REFUNDED->value],
            // `payme_reason`, not `*_code`: AuditRedactor drops code-named keys as secrets
            ['short_code' => $order->short_code, 'provider' => $provider, 'payme_reason' => $reason, 'payment_id' => (int) $payment->id, 'amount' => Money::of($payment->amount)->toString(), 'amount_mismatch' => $mismatch],
            ['actor' => null],
        );

        $orderId = (int) $order->id;
        $paymentId = (int) $payment->id;
        $storeId = (int) $order->store_id;
        DB::afterCommit(fn () => Log::critical("Provider refund on the store's own cashbox for a delivered order: commission kept until a manager reviews it", [
            'provider' => $provider,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'store_id' => $storeId,
            'payme_reason' => $reason,
            'amount_mismatch' => $mismatch,
        ]));
    }

    /**
     * Review: whether the cancel was first reported (cancel_reported_at) before the order's first delivery.
     */
    private static function reportedBeforeDelivery(Order $order, string $firstReported): bool
    {
        $delivered = \App\Models\OrderStatusHistory::where('order_id', $order->id)->where('status', OrderStatus::DELIVERED->value)->min('created_at');
        if ($delivered === null) {
            return false;
        }
        try {
            return \Illuminate\Support\Carbon::parse($firstReported)->lt(\Illuminate\Support\Carbon::parse((string) $delivered));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The review recorded on a payment, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function of(Payment $payment): ?array
    {
        $review = $payment->payload[self::KEY] ?? null;

        return is_array($review) && is_string($review['status'] ?? null) ? $review : null;
    }
}
