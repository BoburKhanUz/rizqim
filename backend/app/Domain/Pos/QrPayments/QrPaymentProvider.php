<?php

namespace App\Domain\Pos\QrPayments;

use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;

/**
 * P6-41/P6-42: one way of taking a QR payment at the till (QrProviders picks the driver): Click and Payme into the
 * store's own merchant account, UzQR through its gateway, or the sandbox (tests, demo).
 */
interface QrPaymentProvider
{
    public function provider(): QrProvider;

    /** The driver's name kept on the request (pos_qr_payments.driver): later calls about it go to the same one. */
    public function driver(): string;

    /** Whether the store can take money this way now (its own merchant account, or a gateway, is set up). */
    public function configured(Store $store): bool;

    /** The QR's payload for this request (`$qr` has its uuid and amount, not saved yet). */
    public function checkout(PosQrPayment $qr, Store $store): QrCheckout;

    /** The request was cancelled or expired before anyone paid: tell the provider, if it keeps one (best effort). */
    public function cancel(PosQrPayment $qr, Store $store): void;

    /** Whether a part of the payment can be given back (otherwise only all of it). */
    public function supportsPartialRefund(): bool;

    /** Give $amount of a paid request back to the customer through the provider. */
    public function refund(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult;

    /**
     * Review P6W4 C2: whether an earlier reversal of $amount went through (a timeout left it unknown): done = the money
     * went back, failed = the provider says it did not, unknown = it does not say (yet).
     */
    public function refundStatus(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult;

    /** Whether the cashier's simulate endpoint may confirm its requests (sandboxes only). */
    public function simulated(): bool;
}
