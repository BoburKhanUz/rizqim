<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
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
 * a manager decides it in the Boshqaruv markazi (App\Services\Admin\Orders\StoreRefundReviewDecision): approve runs
 * RIZQIM's own refund path (OrderStatusService::refund, the commission reversed once), dismiss keeps the commission.
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
     * Records the provider's refund of a paid payment without touching RIZQIM's ledger. The caller holds the order's
     * and the payment's locks inside its transaction and has already marked the payment `refunded`.
     */
    public function hold(Order $order, Payment $payment, string $provider, ?int $reason): void
    {
        $payment->update(['payload' => array_merge($payment->payload ?? [], [
            'refunded_after_delivery' => true,
            self::KEY => [
                'status' => self::PENDING,
                'provider' => $provider,
                'reason' => $reason,
                'reported_at' => now()->toIso8601String(),
            ],
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
            ['short_code' => $order->short_code, 'provider' => $provider, 'reason_code' => $reason, 'payment_id' => (int) $payment->id],
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
            'reason_code' => $reason,
        ]));
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
