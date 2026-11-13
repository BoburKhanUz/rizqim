<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;

/**
 * P6-42: the dynamic UzQR (the national interbank QR, EMVCo merchant-presented) behind a driver. The real bank or
 * provider driver depends on P3-32 (the virtual kassa / acquirer contract); until then SandboxUzQrGateway. A store
 * picks its driver in stores.fiscal_settings.uzqr.driver (config qr_payments.uzqr.drivers).
 *
 * Statuses: `pending`, `paid`, `cancelled`. A real driver confirms through its own callback (or the till's poll,
 * GET /pos/qr/{uuid}, which asks status()) and must not fiscalise the payment itself: Rizqim issues the sale receipt.
 */
interface UzQrGateway
{
    /** Register a dynamic QR for $amount of the request's bill. */
    public function createDynamicQr(Money $amount, PosQrPayment $qr, Store $store): UzQrDynamicQr;

    /**
     * @return array{status: string, transaction_id: string|null}
     */
    public function status(string $id, Store $store): array;

    public function cancel(string $id, Store $store): void;

    public function supportsPartialRefund(): bool;

    public function refund(string $id, Money $amount, Store $store): QrRefundResult;

    /** Review P6W4 C2: whether an earlier refund of $amount went through (done / failed: it did not / unknown). */
    public function refundStatus(string $id, Money $amount, Store $store): QrRefundResult;
}
