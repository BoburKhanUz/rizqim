<?php

namespace App\Domain\Pos\QrPayments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Support\Money;

/**
 * P6-41: a QR request as the till reads it. `payload`: what the QR encodes (print it on the bill, and send it to the
 * customer display as PosDisplayPayment.qr = `display`); `held`: the provider already started taking the money (do
 * not offer a new QR); `simulated`: a sandbox request the demo can confirm (POST .../simulate-paid).
 */
final class QrPaymentPresenter
{
    public const VIEW = 'array{uuid: string, bill_uuid: string, order_uuid: string|null, provider: string, provider_label: string, amount: string, status: string, payload: string, display: array{payload: string, label: string, provider: string}, expires_at: string, held: bool, paid_at: string|null, cancelled_at: string|null, cancel_reason: string|null, payment_uuid: string|null, refunded_amount: string, simulated: bool, created_at: string|null}';

    public function __construct(private readonly QrProviders $providers) {}

    /**
     * @return array{uuid: string, bill_uuid: string, order_uuid: string|null, provider: string, provider_label: string, amount: string, status: string, payload: string, display: array{payload: string, label: string, provider: string}, expires_at: string, held: bool, paid_at: string|null, cancelled_at: string|null, cancel_reason: string|null, payment_uuid: string|null, refunded_amount: string, simulated: bool, created_at: string|null}
     */
    public function present(PosQrPayment $qr): array
    {
        $amount = Money::of($qr->amount)->toString();
        $simulated = $qr->status === QrPaymentStatus::PENDING && $this->providers->simulates($qr);

        return [
            'uuid' => (string) $qr->uuid,
            'bill_uuid' => (string) $qr->bill_uuid,
            'order_uuid' => Order::whereKey($qr->order_id)->value('uuid'),
            'provider' => $qr->provider->value,
            'provider_label' => $qr->provider->label(),
            'amount' => $amount,
            'status' => $qr->status->value,
            'payload' => $qr->checkout_payload,
            'display' => [
                'payload' => $qr->checkout_payload,
                'label' => "{$qr->provider->label()} orqali to'lang: {$amount} so'm",
                'provider' => $qr->provider->value,
            ],
            'expires_at' => $qr->expires_at->toIso8601String(),
            'held' => $qr->held_at !== null,
            'paid_at' => $qr->paid_at?->toIso8601String(),
            'cancelled_at' => $qr->cancelled_at?->toIso8601String(),
            'cancel_reason' => $qr->cancel_reason,
            'payment_uuid' => $qr->payment_id !== null ? Payment::whereKey($qr->payment_id)->value('uuid') : null,
            'refunded_amount' => Money::of($qr->refunded_amount)->toString(),
            'simulated' => $simulated,
            'created_at' => $qr->created_at?->toIso8601String(),
        ];
    }
}
