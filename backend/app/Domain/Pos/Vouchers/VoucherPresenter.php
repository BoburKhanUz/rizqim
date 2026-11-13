<?php

namespace App\Domain\Pos\Vouchers;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Services\Audit\AuditRedactor;
use App\Support\Money;

/**
 * P6-43: a voucher as the till and the partner web show it. The buyer's phone is masked unless `$full` (the owner's
 * own view of one voucher). `print`: what the certificate slip shows (pos_core template): the store, the code and its
 * QR text, the value and the validity.
 */
final class VoucherPresenter
{
    public const VOUCHER = 'array{uuid: string, code: string, kind: string, face_value: string, balance: string, currency: string, valid_from: string, valid_until: string|null, status: string, status_label: string, branch: array{id: int, name: string|null}|null, sale: array{tender: string, card_type: string|null, transaction_id: string|null, reference: string|null, shift_id: int|null, sold_at: string|null, sold_by: array{id: int, name: string|null}|null}, buyer: array{name: string|null, phone: string|null}, note: string|null, fiscal: array{receipt_uuid: string|null, status: string|null}, blocked: array{at: string|null, reason: string|null}|null, print: array{store: string|null, code: string, qr: string, value: string, valid_from: string, valid_until: string|null}, created_at: string|null}';

    public const ENTRY = 'array{uuid: string, type: string, type_label: string, amount: string, balance_after: string, business_date: string|null, bill_uuid: string|null, refund_uuid: string|null, tender: string|null, branch_id: int|null, shift_id: int|null, user: array{id: int, name: string|null}|null, note: string|null, created_at: string|null}';

    /**
     * @return array<string, mixed>
     */
    public function voucher(Voucher $voucher, bool $full = false, ?Branch $at = null): array
    {
        $status = VoucherState::of($voucher, $at);
        $branch = $voucher->branch_id !== null ? Branch::find($voucher->branch_id) : null;
        $seller = $voucher->sold_by !== null ? User::find($voucher->sold_by) : null;

        return [
            'uuid' => (string) $voucher->uuid,
            'code' => (string) $voucher->code,
            'kind' => $voucher->kind->value,
            'face_value' => Money::of($voucher->face_value)->toString(),
            'balance' => Money::of($voucher->balance)->toString(),
            'currency' => (string) $voucher->currency,
            'valid_from' => $voucher->valid_from->toDateString(),
            'valid_until' => $voucher->valid_until?->toDateString(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'branch' => $branch !== null ? ['id' => (int) $branch->id, 'name' => $branch->name] : null,
            'sale' => [
                'tender' => (string) $voucher->sale_tender,
                'card_type' => $voucher->sale_card_type,
                'transaction_id' => $voucher->sale_transaction_id,
                'reference' => $voucher->sale_reference,
                'shift_id' => $voucher->sold_shift_id !== null ? (int) $voucher->sold_shift_id : null,
                'sold_at' => $voucher->sold_at?->toIso8601String(),
                'sold_by' => $seller !== null ? ['id' => (int) $seller->id, 'name' => $seller->name] : null,
            ],
            'buyer' => [
                'name' => $voucher->buyer_name,
                'phone' => $full ? $voucher->buyer_phone : AuditRedactor::maskPhone($voucher->buyer_phone),
            ],
            'note' => $voucher->note,
            'fiscal' => ['receipt_uuid' => $voucher->fiscal_receipt_uuid, 'status' => $voucher->fiscal_status],
            'blocked' => $voucher->blocked_at !== null ? ['at' => $voucher->blocked_at->toIso8601String(), 'reason' => $voucher->block_reason] : null,
            'print' => [
                'store' => Store::whereKey($voucher->store_id)->value('name'),
                'code' => (string) $voucher->code,
                'qr' => (string) $voucher->code,
                'value' => Money::of($voucher->face_value)->toString(),
                'valid_from' => $voucher->valid_from->toDateString(),
                'valid_until' => $voucher->valid_until?->toDateString(),
            ],
            'created_at' => $voucher->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entry(VoucherEntry $entry): array
    {
        $user = $entry->relationLoaded('user') ? $entry->user : ($entry->user_id !== null ? User::find($entry->user_id) : null);

        return [
            'uuid' => (string) $entry->uuid,
            'type' => $entry->type->value,
            'type_label' => $entry->type->label(),
            'amount' => Money::of($entry->amount)->toString(),
            'balance_after' => Money::of($entry->balance_after)->toString(),
            'business_date' => $entry->business_date?->toDateString(),
            'bill_uuid' => $entry->bill_uuid,
            'refund_uuid' => $entry->refund_uuid,
            'tender' => $entry->tender,
            'branch_id' => $entry->branch_id !== null ? (int) $entry->branch_id : null,
            'shift_id' => $entry->shift_id !== null ? (int) $entry->shift_id : null,
            'user' => $user !== null ? ['id' => (int) $user->id, 'name' => $user->name] : null,
            'note' => $entry->note,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
