<?php

namespace App\Domain\Fiscal;

use App\Support\Money;
use App\Support\Quantity;

/**
 * The jsonb form of receipt lines and payments (fiscal_receipts.lines / .payments): money as two-decimal strings,
 * quantities as three-decimal strings, so a retried receipt is rebuilt exactly as it was first sent.
 */
final class FiscalSnapshot
{
    /**
     * @param  list<FiscalLine>  $lines
     * @return list<array<string, mixed>>
     */
    public static function lines(array $lines, TaxRegime $regime): array
    {
        return array_map(fn (FiscalLine $line) => [
            'name' => $line->name,
            'mxik_code' => $line->mxikCode,
            'package_code' => $line->packageCode,
            'unit' => $line->unit,
            'quantity' => $line->quantity->toString(),
            'unit_price' => $line->unitPrice->toString(),
            'amount' => $line->amount->toString(),
            'vat_rate' => $line->vatRate,
            'vat_amount' => $line->vatAmount->toString(),
            // Turnover-tax stores: "QQSsiz", not a 0% VAT sale
            'no_vat' => !$regime->chargesVat(),
            'commissioner_tin' => $line->commissionerTin,
            'marking_code' => $line->markingCode,
        ], $lines);
    }

    /**
     * @param  list<FiscalPayment>  $payments
     * @return list<array<string, mixed>>
     */
    public static function payments(array $payments): array
    {
        return array_map(fn (FiscalPayment $payment) => [
            'form' => $payment->form->value,
            'amount' => $payment->amount->toString(),
            'prepayment' => $payment->prepayment,
            'credit' => $payment->credit,
            'card_type' => $payment->cardType,
            'transaction_id' => $payment->transactionId,
            'card_last4' => $payment->cardLast4,
        ], $payments);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function line(array $row): FiscalLine
    {
        return new FiscalLine(
            name: (string) ($row['name'] ?? ''),
            mxikCode: (string) ($row['mxik_code'] ?? ''),
            packageCode: isset($row['package_code']) ? (string) $row['package_code'] : null,
            unit: (string) ($row['unit'] ?? ''),
            quantity: Quantity::of((string) ($row['quantity'] ?? '0')),
            unitPrice: Money::of((string) ($row['unit_price'] ?? '0')),
            amount: Money::of((string) ($row['amount'] ?? '0')),
            vatRate: (string) ($row['vat_rate'] ?? '0'),
            vatAmount: Money::of((string) ($row['vat_amount'] ?? '0')),
            commissionerTin: isset($row['commissioner_tin']) ? (string) $row['commissioner_tin'] : null,
            markingCode: isset($row['marking_code']) ? (string) $row['marking_code'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function payment(array $row): FiscalPayment
    {
        return new FiscalPayment(
            form: FiscalPaymentForm::from((string) ($row['form'] ?? 'cash')),
            amount: Money::of((string) ($row['amount'] ?? '0')),
            prepayment: (bool) ($row['prepayment'] ?? false),
            // Receipts stored before the flag existed have no credit part
            credit: (bool) ($row['credit'] ?? false),
            cardType: isset($row['card_type']) ? (string) $row['card_type'] : null,
            transactionId: isset($row['transaction_id']) ? (string) $row['transaction_id'] : null,
            // P6-16: receipts stored before it existed have none
            cardLast4: isset($row['card_last4']) ? (string) $row['card_last4'] : null,
        );
    }

    /**
     * The receipt's payment form: the one form money was received in now, `mixed` for more than one. A sale paid
     * entirely by an earlier advance (or on credit) takes the form of those parts.
     *
     * @param  list<FiscalPayment>  $payments
     */
    public static function paymentForm(array $payments): FiscalPaymentForm
    {
        $received = array_filter($payments, fn (FiscalPayment $payment) => !$payment->prepayment && !$payment->credit);
        $forms = array_values(array_unique(array_map(fn (FiscalPayment $payment) => $payment->form->value, $received ?: $payments)));

        if ($forms === []) {
            return FiscalPaymentForm::CASH;
        }

        return count($forms) === 1 ? FiscalPaymentForm::from($forms[0]) : FiscalPaymentForm::MIXED;
    }
}
