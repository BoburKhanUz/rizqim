<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Models\Device;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;

/**
 * P6-42: the dynamic UzQR (the national interbank QR, EMVCo merchant-presented) behind a driver: the real one,
 * UzQrOperatorGateway (`uzqr_operator`, the operator's API with the till's own key), or SandboxUzQrGateway. A store
 * picks its driver in stores.fiscal_settings.uzqr.driver (config qr_payments.uzqr.drivers).
 *
 * Statuses: `pending`, `paid`, `cancelled`, and `mismatch` (the operator says paid, but not our amount: kept for
 * reconciliation, never taken). A driver confirms through the till's poll (GET /pos/qr/{uuid}) and the minute job,
 * which ask status(), and must not fiscalise the payment itself: Rizqim issues the sale receipt.
 *
 * Wave P: every call after the create gets the request itself (`$qr`): the driver uses the key of the till that made
 * it (pos_qr_payments.device_id) and its id at the operator (gateway_reference).
 */
interface UzQrGateway
{
    /**
     * Why this till cannot make a UzQR now (a plain Uzbek message for the cashier), or null when it can.
     */
    public function readyFor(?Device $device, Store $store): ?string;

    /** Register a dynamic QR for $amount of the request's bill. */
    public function createDynamicQr(Money $amount, PosQrPayment $qr, Store $store): UzQrDynamicQr;

    /**
     * @return array{status: string, transaction_id: string|null, details?: array<string, string>}
     */
    public function status(PosQrPayment $qr, Store $store): array;

    public function cancel(PosQrPayment $qr, Store $store): void;

    public function supportsPartialRefund(): bool;

    /** $refundUuid: our refund attempt (the same on a retry of the same refund). */
    public function refund(PosQrPayment $qr, Money $amount, Store $store, string $refundUuid): QrRefundResult;

    /** Review P6W4 C2: whether an earlier refund of $amount went through (done / failed: it did not / unknown). */
    public function refundStatus(PosQrPayment $qr, Money $amount, Store $store, string $refundUuid): QrRefundResult;

    /**
     * Wave P: after the sale receipt of the bill this request paid is fiscalised, its link (the soliq.uz check URL)
     * goes to the operator (SetRetail10's UzQR plugin does it after fiscalisation). Throws on failure (the job retries).
     */
    public function sendReceiptLink(PosQrPayment $qr, string $url, Store $store): void;
}
