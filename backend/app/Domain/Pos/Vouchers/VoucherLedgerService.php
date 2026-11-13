<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Contracts\VoucherLedger;
use App\Models\Branch;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * VoucherLedger over `vouchers` (P6-43): a redemption and its reversal, each under the voucher's row lock with an
 * entry carrying the balance after it. A voucher is spent in any branch of its store; it pays nothing once blocked,
 * used up or past its last business day (valid_until, the branch's business date: a 02:00 payment is still the day
 * before's). One redemption per voucher and bill (409 `voucher_already_on_bill`): a retried payment is answered by
 * its Idempotency-Key before it gets here. A refund onto a voucher past its date extends it to the refund's business day
 * + 30 days; onto a blocked one it is recorded and the voucher stays blocked (review C6, entry note).
 */
class VoucherLedgerService implements VoucherLedger
{
    /** Review C6: how long a refund keeps an expired voucher spendable, from the refund's business day */
    public const REFUND_EXTENSION_DAYS = 30;

    public function redeem(int $storeId, string $code, Money $amount, string $billUuid, ?int $branchId, ?int $userId, ?int $deviceId, ?int $shiftId): array
    {
        $normalized = VoucherCode::normalize($code);
        if ($normalized === null) {
            throw VoucherException::notFound();
        }
        if (!$amount->isPositive()) {
            throw VoucherException::invalid('amount_invalid', "Sertifikatdan to'lov summasi musbat bo'lishi kerak.");
        }

        return DB::transaction(function () use ($storeId, $normalized, $amount, $billUuid, $branchId, $userId, $deviceId, $shiftId) {
            $voucher = Voucher::where('store_id', $storeId)->where('code', $normalized)->where('kind', VoucherKind::GIFT->value)->lockForUpdate()->first();
            if ($voucher === null) {
                throw VoucherException::notFound();
            }

            $branch = $branchId !== null ? Branch::find($branchId) : null;
            $status = VoucherState::of($voucher, $branch);
            if ($status === VoucherStatus::BLOCKED) {
                throw VoucherException::conflict('voucher_blocked', 'Sertifikat bloklangan.');
            }
            if ($status === VoucherStatus::EXPIRED) {
                throw VoucherException::conflict('voucher_expired', "Sertifikat muddati o'tgan (" . $voucher->valid_until?->format('d.m.Y') . ').', ['valid_until' => $voucher->valid_until?->toDateString()]);
            }
            if ($status === VoucherStatus::USED) {
                throw VoucherException::conflict('voucher_used', "Sertifikat to'liq ishlatilgan.", ['balance' => '0.00']);
            }
            if (VoucherEntry::where('voucher_id', $voucher->id)->where('type', VoucherEntryType::REDEMPTION->value)->where('bill_uuid', $billUuid)->exists()) {
                throw VoucherException::conflict('voucher_already_on_bill', "Bu sertifikat shu schyotda ishlatilgan.");
            }

            $balance = Money::of($voucher->balance);
            if ($amount->isGreaterThan($balance)) {
                throw VoucherException::conflict('voucher_insufficient', "Sertifikatda yetarli mablag' yo'q: qoldiq {$balance->toString()} so'm.", ['balance' => $balance->toString()]);
            }

            $after = $balance->minus($amount);
            $voucher->forceFill([
                'balance' => $after->toString(),
                'status' => $after->isZero() ? VoucherStatus::USED : VoucherStatus::ACTIVE,
            ])->save();
            VoucherEntry::create([
                'voucher_id' => $voucher->id,
                'store_id' => $voucher->store_id,
                'branch_id' => $branchId,
                'type' => VoucherEntryType::REDEMPTION,
                'business_date' => VoucherState::today($voucher, $branch),
                'amount' => $amount->toString(),
                'balance_after' => $after->toString(),
                'bill_uuid' => $billUuid,
                'shift_id' => $shiftId,
                'device_id' => $deviceId,
                'user_id' => $userId,
            ]);

            return [
                'uuid' => (string) $voucher->uuid,
                'code' => (string) $voucher->code,
                'form' => (string) $voucher->sale_tender,
                'balance_after' => $after->toString(),
            ];
        });
    }

    public function reverse(string $voucherUuid, Money $amount, string $billUuid, string $refundUuid, ?int $userId, ?int $deviceId, ?int $shiftId): void
    {
        if (!$amount->isPositive()) {
            throw VoucherException::invalid('amount_invalid', "Sertifikatga qaytariladigan summa musbat bo'lishi kerak.");
        }

        DB::transaction(function () use ($voucherUuid, $amount, $billUuid, $refundUuid, $userId, $deviceId, $shiftId) {
            $voucher = Voucher::where('uuid', $voucherUuid)->lockForUpdate()->first();
            if ($voucher === null) {
                throw VoucherException::notFound();
            }
            if (VoucherEntry::where('voucher_id', $voucher->id)->where('type', VoucherEntryType::REFUND->value)->where('refund_uuid', $refundUuid)->exists()) {
                return; // the same refund again
            }

            $entries = VoucherEntry::where('voucher_id', $voucher->id)->where('bill_uuid', $billUuid)->get();
            $taken = Money::sum(...$entries->where('type', VoucherEntryType::REDEMPTION)->pluck('amount')->all())
                ->minus(Money::sum(...$entries->where('type', VoucherEntryType::REFUND)->pluck('amount')->all()));
            if ($amount->isGreaterThan($taken)) {
                throw VoucherException::conflict('reverse_exceeds_applied', "Bu schyot sertifikatdan {$taken->toString()} so'm olgan: undan ko'pini qaytarib bo'lmaydi.", ['applied' => $taken->toString()]);
            }

            // The refund's branch: its shift's
            $branchId = $shiftId !== null ? \App\Models\PosShift::whereKey($shiftId)->value('branch_id') : null;
            $branch = $branchId !== null ? Branch::find((int) $branchId) : null;
            $after = Money::of($voucher->balance)->plus($amount);
            $today = VoucherState::today($voucher, $branch);
            // Review C6: an expired voucher would strand the refunded value, so it gets the refund's business day + 30
            // days; a blocked one keeps the value and stays blocked (the manager unblocks it or not)
            $note = null;
            $extend = [];
            if (VoucherState::of($voucher, $branch) === VoucherStatus::EXPIRED) {
                $until = \Carbon\CarbonImmutable::parse($today)->addDays(self::REFUND_EXTENSION_DAYS);
                $extend = ['valid_until' => $until->toDateString()];
                $note = "Muddati o'tgan edi: " . $until->format('d.m.Y') . ' gacha uzaytirildi.';
            } elseif ($voucher->status === VoucherStatus::BLOCKED) {
                $note = 'Sertifikat bloklangan: summa unda saqlandi, menejer hal qiladi.';
            }
            $voucher->forceFill($extend + [
                'balance' => $after->toString(),
                // A used-up or expired voucher holds money again (an expired one with its new date); a blocked one
                // stays blocked
                'status' => in_array($voucher->status, [VoucherStatus::USED, VoucherStatus::EXPIRED], true) ? VoucherStatus::ACTIVE : $voucher->status,
            ])->save();
            VoucherEntry::create([
                'voucher_id' => $voucher->id,
                'store_id' => $voucher->store_id,
                'type' => VoucherEntryType::REFUND,
                'branch_id' => $branch?->id,
                'business_date' => $today,
                'amount' => $amount->toString(),
                'balance_after' => $after->toString(),
                'bill_uuid' => $billUuid,
                'refund_uuid' => $refundUuid,
                'note' => $note,
                'shift_id' => $shiftId,
                'device_id' => $deviceId,
                'user_id' => $userId,
            ]);
        });
    }
}
