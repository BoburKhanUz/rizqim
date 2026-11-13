<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Billing\RefundService;
use App\Models\PosQrPayment;
use App\Models\PosQrRefundAttempt;
use App\Models\PosRefund;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P6-41: a refund of a QR payment the provider confirmed goes back through that provider: Click's payment reversal,
 * Payme's receipts.cancel, the UzQR gateway's refund, the sandbox. The provider refusing keeps the refund blocked with
 * a plain message — a QR payment is never silently refunded in cash. Click and Payme give back only the whole payment
 * (422 qr_partial_refund_unsupported). A QR payment the cashier confirmed by hand (a static QR) has no request: it
 * is refunded as before (by hand, outside Rizqim).
 *
 * Review P6W4 C2: in two phases, so the shift's money lock is never held while a provider is asked. RefundService
 * commits an attempt (pos_qr_refund_attempts, `requested`) and releases the lock; ask() calls the provider outside any
 * transaction; RefundService::settleQr() then records the refund in a short transaction (`refunded`: the PosRefund has
 * the attempt's uuid), or the attempt becomes `failed` (409 qr_refund_failed: nothing changed) or `unknown` (409
 * qr_refund_unknown: the provider did not answer, the money may have gone back). A retry of the refund (the same
 * amount: the same Idempotency-Key, or another tap) resumes the open attempt: an `unknown` one first asks the provider's
 * status ("already reversed" is success; "not reversed" sends the reversal again), a `failed` one sends it again. The
 * minute job (pos:expire-qr-payments) asks the status of `unknown` attempts and records the ones the provider reversed
 * (never sends a reversal itself). While an attempt is open (or failed less than an hour ago) Payme's CancelTransaction
 * callback that receipts.cancel triggers is accepted; if it committed the cancel, the attempt ends `refunded` even
 * when receipts.cancel answered an error or nothing.
 */
final class QrRefunds
{
    /** How long a `requested` attempt counts as still being asked (the provider call's 5 s + 20 s, with a margin). */
    public const IN_FLIGHT_SECONDS = 60;

    /** How long the same cashier may send a refused reversal again without a new manager approval. */
    public const RETRY_MINUTES = 10;

    /** How often the minute job asks about one `unknown` attempt at most. */
    public const CHECK_SECONDS = 60;

    public function __construct(private readonly QrProviders $providers) {}

    /**
     * The attempt a refund of $amount continues (inside RefundService's first transaction, the payment's row locked):
     * the open one of the same amount, or the same user's failed one of the last minutes; null for a new refund.
     * An attempt still being asked, or an open one of another amount: 409 qr_refund_unknown.
     *
     * @throws PosActionException
     */
    public function resumable(int $paymentId, Money $amount, int $userId): ?PosQrRefundAttempt
    {
        /** @var Collection<int, PosQrRefundAttempt> $open */
        $open = PosQrRefundAttempt::where('payment_id', $paymentId)
            ->whereIn('status', [QrRefundStatus::REQUESTED->value, QrRefundStatus::UNKNOWN->value])
            ->lockForUpdate()->orderBy('id')->get();
        foreach ($open as $attempt) {
            if ($attempt->status === QrRefundStatus::REQUESTED && $attempt->updated_at?->gt(now()->subSeconds(self::IN_FLIGHT_SECONDS))) {
                throw self::unknownError('qr_refund_in_progress');
            }
        }
        $resume = $open->first(fn (PosQrRefundAttempt $attempt) => Money::of($attempt->amount)->equals($amount));
        if ($open->isNotEmpty() && $resume === null) {
            throw self::unknownError('qr_refund_pending');
        }

        return $resume ?? PosQrRefundAttempt::where('payment_id', $paymentId)->where('user_id', $userId)
            ->where('status', QrRefundStatus::FAILED->value)->where('amount', $amount->toString())
            ->where('updated_at', '>', now()->subMinutes(self::RETRY_MINUTES))
            ->lockForUpdate()->orderByDesc('id')->first();
    }

    /**
     * Refuses a part the provider cannot give back (Click, Payme: the whole payment only).
     *
     * @throws PosActionException
     */
    public function assertAmount(PosQrPayment $qr, Money $amount): void
    {
        $left = Money::of($qr->amount)->minus($qr->refunded_amount);
        if ($amount->isLessThan($left) && !$this->providers->of($qr)->supportsPartialRefund()) {
            throw PosActionException::invalid('qr_partial_refund_unsupported', "{$qr->provider->label()} to'lovi faqat to'liq qaytariladi: {$left->toString()} so'm.", ['left' => $left->toString(), 'provider' => $qr->provider->value]);
        }
    }

    /**
     * Asks the provider, OUTSIDE any transaction (the attempt is committed `requested`). $statusFirst: the attempt
     * may already have been reversed (unknown before): its status is asked first; $send: a "not reversed" answer
     * sends the reversal (a person's retry; the job only asks).
     */
    public function ask(PosQrRefundAttempt $attempt, bool $statusFirst, bool $send = true): QrRefundResult
    {
        $qr = PosQrPayment::findOrFail($attempt->qr_payment_id);
        $store = Store::findOrFail($qr->store_id);
        $amount = Money::of($attempt->amount);
        try {
            $driver = $this->providers->of($qr);
            $result = $statusFirst ? $driver->refundStatus($qr, $store, $amount, (string) $attempt->uuid) : null;
            if ($send && ($result === null || (!$result->ok && !$result->unknown))) {
                PosQrRefundAttempt::whereKey($attempt->id)->increment('provider_calls');
                $result = $driver->refund($qr, $store, $amount, (string) $attempt->uuid);
            }
        } catch (\Throwable $e) {
            Log::error('QR refund not answered: ' . $e->getMessage(), ['qr' => $qr->uuid, 'attempt' => $attempt->uuid]);
            $result = QrRefundResult::unknown("{$qr->provider->label()} javob bermadi");
        }
        $result ??= QrRefundResult::unknown("{$qr->provider->label()} javob bermadi");

        // Payme's CancelTransaction callback for this payment committed the cancel: the money went back
        if (!$result->ok && self::cancelledAtPayme($qr->refresh())) {
            return QrRefundResult::done($qr->provider_transaction_id);
        }

        return $result;
    }

    /**
     * Records a done reversal on the request (inside RefundService's finalising transaction).
     */
    public function settle(PosQrRefundAttempt $attempt, PosRefund $refund, QrRefundResult $result): void
    {
        $qr = PosQrPayment::whereKey($attempt->qr_payment_id)->lockForUpdate()->firstOrFail();
        $amount = Money::of($attempt->amount);
        $refunded = Money::of($qr->refunded_amount)->plus($amount);
        $payload = $qr->provider_payload ?? [];
        $payload['refunds'] = [...(array) ($payload['refunds'] ?? []), ['refund' => $refund->uuid, 'amount' => $amount->toString(), 'reference' => $result->reference, 'at' => now()->toIso8601String()]];
        $qr->forceFill([
            'refunded_amount' => $refunded->toString(),
            'refunded_at' => now(),
            'status' => $refunded->isGreaterThanOrEqualTo($qr->amount) ? QrPaymentStatus::REFUNDED : $qr->status,
            'provider_payload' => $payload,
        ])->save();
        $attempt->forceFill([
            'status' => QrRefundStatus::REFUNDED,
            'refund_id' => $refund->id,
            'reference' => $result->reference !== null ? mb_substr($result->reference, 0, 100) : null,
            'message' => null,
            'resolved_at' => now(),
        ])->save();
    }

    /**
     * The minute job: asks the provider about `unknown` attempts (and `requested` ones whose request died) and records
     * the reversed ones as refunds; "not reversed" makes them `failed` (the cashier may refund again). Returns how
     * many were resolved.
     */
    public function reconcile(): int
    {
        $resolved = 0;
        PosQrRefundAttempt::query()
            ->where(fn ($query) => $query->where('status', QrRefundStatus::UNKNOWN->value)
                ->orWhere(fn ($query) => $query->where('status', QrRefundStatus::REQUESTED->value)->where('updated_at', '<', now()->subSeconds(self::IN_FLIGHT_SECONDS))))
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<', now()->subSeconds(self::CHECK_SECONDS)))
            ->orderBy('id')->limit(20)->get()
            ->each(function (PosQrRefundAttempt $attempt) use (&$resolved) {
                $claimed = DB::transaction(function () use ($attempt) {
                    $attempt = PosQrRefundAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                    $stale = $attempt->status === QrRefundStatus::REQUESTED && $attempt->updated_at?->lt(now()->subSeconds(self::IN_FLIGHT_SECONDS));
                    if ($attempt->status !== QrRefundStatus::UNKNOWN && !$stale) {
                        return null;
                    }
                    $attempt->forceFill(['status' => QrRefundStatus::REQUESTED, 'checked_at' => now()])->save();

                    return $attempt;
                });
                if ($claimed === null) {
                    return;
                }
                try {
                    app(RefundService::class)->settleQr($claimed, $this->ask($claimed, true, false));
                    $resolved++;
                } catch (PosActionException $e) {
                    if ($e->reason === 'qr_refund_failed') {
                        $resolved++;
                    }
                } catch (\Throwable $e) {
                    Log::error('QR refund reconcile failed: ' . $e->getMessage(), ['attempt' => $claimed->uuid]);
                    PosQrRefundAttempt::whereKey($claimed->id)->where('status', QrRefundStatus::REQUESTED->value)->update(['status' => QrRefundStatus::UNKNOWN->value]);
                }
            });

        return $resolved;
    }

    /**
     * Whether a refund is asking (or just asked) the provider to cancel this request's payment: Payme's
     * CancelTransaction callback for a performed transaction is accepted then.
     */
    public static function requested(PosQrPayment $qr): bool
    {
        return PosQrRefundAttempt::where('qr_payment_id', $qr->id)
            ->where(fn ($query) => $query->whereIn('status', [QrRefundStatus::REQUESTED->value, QrRefundStatus::UNKNOWN->value])
                ->orWhere(fn ($query) => $query->where('status', QrRefundStatus::FAILED->value)->where('updated_at', '>', now()->subHour())))
            ->exists();
    }

    /**
     * Payme's callback committed the cancel of this payment: a refused attempt is open again (`unknown`), so the retry
     * or the minute job records the refund (the money went back).
     */
    public static function cancelledByProvider(PosQrPayment $qr): void
    {
        PosQrRefundAttempt::where('qr_payment_id', $qr->id)->where('status', QrRefundStatus::FAILED->value)
            ->where('updated_at', '>', now()->subHour())
            ->update(['status' => QrRefundStatus::UNKNOWN->value, 'checked_at' => null, 'updated_at' => now()]);
    }

    public static function unknownError(string $code = 'qr_refund_unknown'): PosActionException
    {
        return PosActionException::conflict('qr_refund_unknown', "To'lov tizimi javob bermadi — holat tekshirilmoqda. Pul qaytgan bo'lsa, qaytarish o'zi yoziladi; birozdan so'ng \"Holatni tekshirish\" bilan qayta urinib ko'ring.", ['state' => $code]);
    }

    private static function cancelledAtPayme(PosQrPayment $qr): bool
    {
        return $qr->provider === QrProvider::PAYME && isset(((array) (($qr->provider_payload ?? [])['payme'] ?? []))['cancel_time'])
            && Money::of($qr->refunded_amount)->isZero();
    }
}
