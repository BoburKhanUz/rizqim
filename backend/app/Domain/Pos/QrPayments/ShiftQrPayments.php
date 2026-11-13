<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Contracts\PosTender;
use App\Models\Payment;
use App\Models\PosShift;
use App\Support\Money;

/**
 * P6-41: the X/Z report's `qr_providers`: the shift's `qr` payments by who took them — click, payme, uzqr (a dynamic
 * QR confirmed by the provider) and `manual` (a static QR the cashier confirmed by hand). Counts and amounts only:
 * the providers' commission is not estimated.
 */
final class ShiftQrPayments
{
    public const SHAPE = 'list<array{provider: string, count: int, amount: string}>';

    /**
     * @return list<array{provider: string, count: int, amount: string}>
     */
    public function forShift(PosShift $shift): array
    {
        $rows = Payment::query()
            ->where('payments.shift_id', $shift->id)
            ->where('payments.provider', PosTender::QR->value)
            ->whereIn('payments.status', ['paid', 'refunded'])
            ->leftJoin('pos_qr_payments', 'pos_qr_payments.payment_id', '=', 'payments.id')
            ->get(['payments.amount', 'pos_qr_payments.provider as qr_provider']);

        $result = [];
        foreach ([...QrProvider::values(), 'manual'] as $provider) {
            $of = $rows->filter(fn ($row) => ($row->getAttribute('qr_provider') ?? 'manual') === $provider);
            $sum = Money::zero();
            foreach ($of as $row) {
                $sum = $sum->plus((string) $row->amount);
            }
            $result[] = ['provider' => $provider, 'count' => $of->count(), 'amount' => $sum->toString()];
        }

        return $result;
    }
}
