<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Support\Money;

/**
 * P6-54: what the event's payment schedule (P6-53) holds as paid advances for the final settlement: every schedule row
 * that is `paid` (money taken at the till with its ADVANCE receipt, or a bank transfer recorded by the manager), less
 * what of it was refunded or retained. The oldest first (paid_at, id): the final sale offsets them in that order.
 */
final class BanquetAdvances
{
    /**
     * @return list<array{uuid: string, tender: string, amount: Money, paid_at: string|null, receipt_uuid: string|null}>
     */
    public static function of(BanquetEvent $event): array
    {
        $rows = BanquetPayment::where('banquet_event_id', $event->id)
            ->where('status', BanquetPaymentStatus::PAID->value)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $amount = Money::of($row->amount)->minus($row->refunded_amount)->minus($row->retained_amount);
            if (!$amount->isPositive()) {
                continue;
            }
            $out[] = [
                'uuid' => (string) $row->uuid,
                'tender' => (string) ($row->tender ?? 'cash'),
                'amount' => $amount,
                'paid_at' => $row->paid_at?->toJSON(),
                'receipt_uuid' => $row->fiscal_receipt_uuid,
            ];
        }

        return $out;
    }

    public static function total(BanquetEvent $event): Money
    {
        return Money::sum(...array_column(self::of($event), 'amount'));
    }

    /**
     * The receipt form of an advance's tender. A bank transfer (no receipt when it came in, P6-53) shows as card: a
     * cashless payment (owner/accountant to confirm).
     */
    public static function form(string $tender): FiscalPaymentForm
    {
        return match ($tender) {
            'card', 'bank_transfer' => FiscalPaymentForm::CARD,
            'qr' => FiscalPaymentForm::QR,
            default => FiscalPaymentForm::CASH,
        };
    }

    /**
     * `amount` of the advances spread over their forms, oldest advance first.
     *
     * @return list<array{form: FiscalPaymentForm, amount: Money}>
     */
    public static function forms(BanquetEvent $event, Money $amount): array
    {
        $byForm = [];
        $left = $amount;
        foreach (self::of($event) as $advance) {
            if (!$left->isPositive()) {
                break;
            }
            $part = Money::min($left, $advance['amount']);
            $form = self::form($advance['tender'])->value;
            $byForm[$form] = ($byForm[$form] ?? Money::zero())->plus($part);
            $left = $left->minus($part);
        }
        if ($left->isPositive()) {
            // More offset than the rows hold now (a row refunded since): the rest in cash, so the receipt still adds up
            $byForm[FiscalPaymentForm::CASH->value] = ($byForm[FiscalPaymentForm::CASH->value] ?? Money::zero())->plus($left);
        }

        return array_map(fn (string $form, Money $sum) => ['form' => FiscalPaymentForm::from($form), 'amount' => $sum], array_keys($byForm), array_values($byForm));
    }
}
