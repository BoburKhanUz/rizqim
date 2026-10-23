<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Fiscal\FiscalSnapshot;
use App\Models\FiscalReceipt;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * P3-26: the receipt as registered, for the till to print (POS_SPEC 2.11): the fiscal sign, receipt number, QR link,
 * the fiscal module (terminal) id, whether it is non-fiscal (sandbox / fiscal mode off), when it was issued, its type
 * and payment form, and its lines and payments exactly as sent to the provider. It travels inside the `fiscal` object
 * of bill payments, fiscal retries, refunds, booking deposits and nasiya repayments, and GET /pos/fiscal-receipts/{uuid}
 * reads it again (pos.take_payment). No provider internals (driver payload, retry state) reach the till.
 */
final class PosFiscalReceipt
{
    public const LINE = 'array{name: string, mxik_code: string, package_code: string|null, unit: string, quantity: string, unit_price: string, amount: string, vat_rate: string, vat_amount: string, no_vat: bool, commissioner_tin: string|null, marking_code: string|null}';

    public const PAYMENT = 'array{form: \'cash\'|\'card\'|\'qr\', amount: string, prepayment: bool, credit: bool, card_type: string|null, transaction_id: string|null}';

    public const RECEIPT = 'array{uuid: string, type: \'sale\'|\'refund\'|\'advance\'|\'credit\', status: \'pending\'|\'issued\'|\'failed\', payment_form: \'cash\'|\'card\'|\'qr\'|\'mixed\', non_fiscal: bool, total: string, vat_total: string, fiscal_sign: string|null, receipt_number: string|null, qr_url: string|null, terminal_id: string|null, error: string|null, issued_at: string|null, created_at: string|null, branch_id: int|null, original_receipt_uuid: string|null, lines: list<' . self::LINE . '>, payments: list<' . self::PAYMENT . '>}';

    /** The `fiscal` object of a bill, refund, deposit or repayment: the receipt's state and, once there is one, the receipt itself. */
    public const FISCAL = 'array{status: string|null, receipt_uuid: string|null, error: string|null, receipt: ' . self::RECEIPT . '|null}';

    /**
     * @return array<string, mixed>
     */
    public static function present(FiscalReceipt $receipt): array
    {
        $receipt->loadMissing('originalReceipt:id,uuid');

        return [
            'uuid' => (string) $receipt->uuid,
            'type' => $receipt->type->value,
            'status' => $receipt->status->value,
            'payment_form' => $receipt->payment_form->value,
            'non_fiscal' => (bool) $receipt->non_fiscal,
            'total' => Money::of($receipt->total)->toString(),
            'vat_total' => Money::of($receipt->vat_total)->toString(),
            'fiscal_sign' => $receipt->fiscal_sign,
            'receipt_number' => $receipt->receipt_number,
            'qr_url' => $receipt->qr_url,
            'terminal_id' => $receipt->terminal_id,
            'error' => $receipt->error,
            'issued_at' => $receipt->issued_at?->toIso8601String(),
            'created_at' => $receipt->created_at?->toIso8601String(),
            'branch_id' => $receipt->branch_id !== null ? (int) $receipt->branch_id : null,
            'original_receipt_uuid' => $receipt->originalReceipt?->uuid,
            'lines' => array_values(array_map(fn (array $row) => self::line($row), array_filter((array) ($receipt->lines ?? []), 'is_array'))),
            // Through the snapshot, so a receipt stored before a flag existed answers in today's shape (credit: false)
            'payments' => FiscalSnapshot::payments(array_map(
                fn (array $row) => FiscalSnapshot::payment($row),
                array_values(array_filter((array) ($receipt->payments ?? []), 'is_array')),
            )),
        ];
    }

    /**
     * The receipt behind a uuid of the store, presented; null when there is none (yet), it is another store's, or the
     * uuid is not one (a `fiscal_status` of `error` stores no uuid).
     *
     * @return array<string, mixed>|null
     */
    public static function find(?string $uuid, int $storeId): ?array
    {
        if ($uuid === null || !Str::isUuid($uuid)) {
            return null;
        }

        $receipt = FiscalReceipt::with('originalReceipt:id,uuid')->where('store_id', $storeId)->where('uuid', $uuid)->first();

        return $receipt instanceof FiscalReceipt ? self::present($receipt) : null;
    }

    /**
     * The `fiscal` object: what the bill (refund, deposit, repayment) records about its receipt, plus the receipt.
     *
     * @return array{status: string|null, receipt_uuid: string|null, error: string|null, receipt: array<string, mixed>|null}
     */
    public static function fiscal(?string $status, ?string $receiptUuid, ?string $error, int $storeId): array
    {
        return [
            'status' => $status,
            'receipt_uuid' => $receiptUuid,
            'error' => $error,
            'receipt' => self::find($receiptUuid, $storeId),
        ];
    }

    /**
     * One line in today's shape (keys a receipt stored earlier may lack are filled in).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function line(array $row): array
    {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'mxik_code' => (string) ($row['mxik_code'] ?? ''),
            'package_code' => isset($row['package_code']) && $row['package_code'] !== '' ? (string) $row['package_code'] : null,
            'unit' => (string) ($row['unit'] ?? ''),
            'quantity' => (string) ($row['quantity'] ?? '0'),
            'unit_price' => (string) ($row['unit_price'] ?? '0.00'),
            'amount' => (string) ($row['amount'] ?? '0.00'),
            'vat_rate' => (string) ($row['vat_rate'] ?? '0'),
            'vat_amount' => (string) ($row['vat_amount'] ?? '0.00'),
            'no_vat' => (bool) ($row['no_vat'] ?? false),
            'commissioner_tin' => isset($row['commissioner_tin']) ? (string) $row['commissioner_tin'] : null,
            'marking_code' => isset($row['marking_code']) ? (string) $row['marking_code'] : null,
        ];
    }
}
