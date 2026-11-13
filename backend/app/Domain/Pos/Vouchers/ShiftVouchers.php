<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Contracts\PosTender;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\VoucherEntry;
use App\Support\Money;

/**
 * P6-43: the gift certificates of a shift's X/Z report, apart from the sales (a certificate sold is an advance, not
 * revenue): `sold` — certificates sold on the shift (their ADVANCE receipts; `cash` is what went into the drawer and
 * counts toward the expected cash, read from voucher_entries like the booking deposits, never copied into
 * cash_movements); `redeemed` — bill parts paid with a certificate (the sale receipt's prepayment part, as a deposit
 * offset; also in `by_tender` as `voucher`).
 */
final class ShiftVouchers
{
    public const SHAPE = 'array{sold: array{count: int, amount: string, cash: string, card: string, qr: string}, redeemed: array{count: int, amount: string}}';

    /**
     * @return array{sold: array{count: int, amount: string, cash: string, card: string, qr: string}, redeemed: array{count: int, amount: string}}
     */
    public function forShift(PosShift $shift): array
    {
        $sales = VoucherEntry::where('shift_id', $shift->id)->where('type', VoucherEntryType::SALE->value)->get(['amount', 'tender']);
        $redeemed = Payment::where('shift_id', $shift->id)->where('provider', PosTender::VOUCHER->value)->whereIn('status', ['paid', 'refunded'])->pluck('amount');
        $sum = fn ($amounts) => Money::sum(...$amounts->all())->toString();

        return [
            'sold' => [
                'count' => $sales->count(),
                'amount' => $sum($sales->pluck('amount')),
                'cash' => $sum($sales->where('tender', PosTender::CASH->value)->pluck('amount')),
                'card' => $sum($sales->where('tender', PosTender::CARD->value)->pluck('amount')),
                'qr' => $sum($sales->where('tender', PosTender::QR->value)->pluck('amount')),
            ],
            'redeemed' => ['count' => $redeemed->count(), 'amount' => $sum($redeemed)],
        ];
    }

    /**
     * Cash the shift's certificate sales put into its drawer.
     */
    public function cash(PosShift $shift): Money
    {
        return Money::sum(...VoucherEntry::where('shift_id', $shift->id)->where('type', VoucherEntryType::SALE->value)
            ->where('tender', PosTender::CASH->value)->pluck('amount')->all());
    }
}
