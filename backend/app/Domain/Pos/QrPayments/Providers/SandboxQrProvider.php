<?php

namespace App\Domain\Pos\QrPayments\Providers;

use App\Domain\Pos\QrPayments\QrCheckout;
use App\Domain\Pos\QrPayments\QrPaymentProvider;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;

/**
 * P6-41: Click or Payme on a demo or e2e stand (config qr_payments.sandbox): no merchant account, a deterministic
 * payload on a reserved, never-resolving host (nobody can pay it for real), confirmed by POST
 * /pos/qr/{uuid}/simulate-paid, refunds always succeed (in part too).
 */
final class SandboxQrProvider implements QrPaymentProvider
{
    public const HOST = 'https://qr-sandbox.invalid';

    public function __construct(private readonly QrProvider $provider) {}

    public function provider(): QrProvider
    {
        return $this->provider;
    }

    public function driver(): string
    {
        return 'sandbox';
    }

    public function configured(Store $store): bool
    {
        return true;
    }

    public function checkout(PosQrPayment $qr, Store $store): QrCheckout
    {
        return new QrCheckout(self::HOST . '/' . $this->provider->value . '?account=' . $qr->account() . '&amount=' . Money::of($qr->amount)->toString());
    }

    public function cancel(PosQrPayment $qr, Store $store): void {}

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function refund(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        return QrRefundResult::done('sandbox-refund-' . $refundUuid);
    }

    public function refundStatus(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        return QrRefundResult::done('sandbox-refund-' . $refundUuid);
    }

    public function simulated(): bool
    {
        return true;
    }
}
