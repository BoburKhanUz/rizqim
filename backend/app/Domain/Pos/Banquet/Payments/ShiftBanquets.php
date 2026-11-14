<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Contracts\PosTender;
use App\Models\BanquetPaymentEntry;
use App\Models\PosShift;
use App\Support\Money;

/**
 * P6-53: the banquet advances of a shift's X/Z report, apart from the sales (a schedule payment is an advance, not
 * revenue; the event's final sale receipt offsets it as prepayment): `taken` — payments of schedule rows taken on the
 * shift (their ADVANCE receipts; `cash` went into the drawer and counts toward the expected cash), `refunded` — refunds
 * of cancelled events made on the shift (`cash` was paid from the drawer, off the expected cash). Read from
 * banquet_payment_entries like the vouchers (never copied into cash_movements, so never counted twice). Bank transfers
 * belong to no shift.
 */
final class ShiftBanquets
{
    public const SHAPE = 'array{taken: array{count: int, amount: string, cash: string, card: string, qr: string}, refunded: array{count: int, amount: string, cash: string}}';

    /**
     * @return array{taken: array{count: int, amount: string, cash: string, card: string, qr: string}, refunded: array{count: int, amount: string, cash: string}}
     */
    public function forShift(PosShift $shift): array
    {
        $entries = BanquetPaymentEntry::where('shift_id', $shift->id)
            ->whereIn('type', [BanquetEntryType::PAYMENT->value, BanquetEntryType::REFUND->value])
            ->get(['type', 'amount', 'tender']);
        $taken = $entries->where('type', BanquetEntryType::PAYMENT);
        $refunded = $entries->where('type', BanquetEntryType::REFUND);
        $sum = fn ($rows) => Money::sum(...$rows->pluck('amount')->all())->toString();

        return [
            'taken' => [
                'count' => $taken->count(),
                'amount' => $sum($taken),
                'cash' => $sum($taken->where('tender', PosTender::CASH->value)),
                'card' => $sum($taken->where('tender', PosTender::CARD->value)),
                'qr' => $sum($taken->where('tender', PosTender::QR->value)),
            ],
            'refunded' => [
                'count' => $refunded->count(),
                'amount' => $sum($refunded),
                'cash' => $sum($refunded->where('tender', PosTender::CASH->value)),
            ],
        ];
    }

    /**
     * The drawer's net cash from banquet money on the shift: cash advances taken less cash refunds paid.
     *
     * @return array{taken: Money, refunded: Money}
     */
    public function cash(PosShift $shift): array
    {
        $rows = BanquetPaymentEntry::where('shift_id', $shift->id)->where('tender', PosTender::CASH->value)
            ->whereIn('type', [BanquetEntryType::PAYMENT->value, BanquetEntryType::REFUND->value])->get(['type', 'amount']);

        return [
            'taken' => Money::sum(...$rows->where('type', BanquetEntryType::PAYMENT)->pluck('amount')->all()),
            'refunded' => Money::sum(...$rows->where('type', BanquetEntryType::REFUND)->pluck('amount')->all()),
        ];
    }
}
