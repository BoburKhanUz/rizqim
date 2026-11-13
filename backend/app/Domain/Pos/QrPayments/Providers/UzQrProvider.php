<?php

namespace App\Domain\Pos\QrPayments\Providers;

use App\Domain\Pos\QrPayments\QrCheckout;
use App\Domain\Pos\QrPayments\QrPaymentProvider;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Domain\Pos\QrPayments\UzQr\UzQrGateway;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;

/**
 * P6-42: UzQR through the store's gateway (`uzqr:<driver>`); without one the provider is not configured.
 */
final class UzQrProvider implements QrPaymentProvider
{
    public function __construct(
        private readonly ?string $gatewayName,
        private readonly ?UzQrGateway $gateway,
    ) {}

    public function provider(): QrProvider
    {
        return QrProvider::UZQR;
    }

    public function driver(): string
    {
        return 'uzqr:' . ($this->gatewayName ?? 'none');
    }

    public function gateway(): ?UzQrGateway
    {
        return $this->gateway;
    }

    public function configured(Store $store): bool
    {
        return $this->gateway !== null;
    }

    public function checkout(PosQrPayment $qr, Store $store): QrCheckout
    {
        $qr = $this->gateway?->createDynamicQr(Money::of($qr->amount), $qr, $store)
            ?? throw new \LogicException('UzQR gateway is not configured');

        return new QrCheckout($qr->payload, $qr->id);
    }

    public function cancel(PosQrPayment $qr, Store $store): void
    {
        if ($this->gateway !== null && $qr->gateway_reference !== null) {
            $this->gateway->cancel($qr->gateway_reference, $store);
        }
    }

    public function supportsPartialRefund(): bool
    {
        return $this->gateway?->supportsPartialRefund() ?? false;
    }

    public function refund(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        if ($this->gateway === null || $qr->gateway_reference === null) {
            return QrRefundResult::failed("UzQR shlyuzi sozlanmagan");
        }

        return $this->gateway->refund($qr->gateway_reference, $amount, $store);
    }

    public function refundStatus(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        if ($this->gateway === null || $qr->gateway_reference === null) {
            return QrRefundResult::unknown("UzQR shlyuzi sozlanmagan");
        }

        return $this->gateway->refundStatus($qr->gateway_reference, $amount, $store);
    }

    public function simulated(): bool
    {
        return $this->gatewayName === 'sandbox';
    }
}
