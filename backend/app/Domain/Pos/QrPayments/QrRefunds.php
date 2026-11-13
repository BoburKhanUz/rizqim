<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Billing\PosActionException;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosRefund;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * P6-41: a refund of a QR payment the provider confirmed goes back through that provider (RefundService asks, inside
 * its transaction, after every other check): Click's payment reversal, Payme's receipts.cancel, the UzQR gateway's
 * refund, the sandbox. The provider refusing (or not answering) keeps the refund blocked with a plain message — a QR
 * payment is never silently refunded in cash. Click and Payme give back only the whole payment
 * (422 qr_partial_refund_unsupported). A QR payment the cashier confirmed by hand (a static QR) has no request: it
 * is refunded as before (by hand, outside Rizqim).
 *
 * While the provider is asked, the request is flagged (cache, a few minutes) so Payme's CancelTransaction callback
 * that receipts.cancel triggers is accepted; a CancelTransaction nobody asked for is refused (-31007).
 */
final class QrRefunds
{
    private const FLAG_SECONDS = 300;

    public function __construct(private readonly QrProviders $providers) {}

    /**
     * @throws PosActionException
     */
    public function reverse(Payment $payment, Money $amount, PosRefund $refund): void
    {
        // Not locked: RefundService holds the payment's row (refunds of it are serialised), and Payme's CancelTransaction
        // callback, which receipts.cancel triggers while we wait, writes to this row
        $qr = PosQrPayment::where('payment_id', $payment->id)->first();
        if ($qr === null) {
            return;
        }

        $left = Money::of($qr->amount)->minus($qr->refunded_amount);
        $driver = $this->providers->of($qr);
        if ($amount->isLessThan($left) && !$driver->supportsPartialRefund()) {
            throw PosActionException::invalid('qr_partial_refund_unsupported', "{$qr->provider->label()} to'lovi faqat to'liq qaytariladi: {$left->toString()} so'm.", ['left' => $left->toString(), 'provider' => $qr->provider->value]);
        }

        Cache::put(self::flag($qr), true, self::FLAG_SECONDS);
        try {
            $result = $driver->refund($qr, Store::findOrFail($qr->store_id), $amount, (string) $refund->uuid);
        } catch (\Throwable $e) {
            Log::error('QR refund failed: ' . $e->getMessage(), ['qr' => $qr->uuid]);
            $result = QrRefundResult::failed("{$qr->provider->label()} bilan aloqa bo'lmadi");
        }
        if (!$result->ok) {
            Cache::forget(self::flag($qr));

            throw PosActionException::conflict('qr_refund_failed', "{$qr->provider->label()} pulni qaytarmadi ({$result->message}). Qaytarish amalga oshmadi: keyinroq qayta urinib ko'ring yoki pulni {$qr->provider->label()} kabinetidan qaytaring.", ['provider' => $qr->provider->value]);
        }

        $qr->refresh();
        $refunded = Money::of($qr->refunded_amount)->plus($amount);
        $payload = $qr->provider_payload ?? [];
        $payload['refunds'] = [...(array) ($payload['refunds'] ?? []), ['refund' => $refund->uuid, 'amount' => $amount->toString(), 'reference' => $result->reference, 'at' => now()->toIso8601String()]];
        $qr->forceFill([
            'refunded_amount' => $refunded->toString(),
            'refunded_at' => now(),
            'status' => $refunded->isGreaterThanOrEqualTo($qr->amount) ? QrPaymentStatus::REFUNDED : $qr->status,
            'provider_payload' => $payload,
        ])->save();
    }

    /**
     * Whether a refund is asking the provider to cancel this request's payment right now.
     */
    public static function requested(PosQrPayment $qr): bool
    {
        return (bool) Cache::get(self::flag($qr), false);
    }

    private static function flag(PosQrPayment $qr): string
    {
        return 'pos-qr-refund:' . $qr->uuid;
    }
}
