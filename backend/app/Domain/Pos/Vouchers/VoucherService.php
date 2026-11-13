<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Booking\BookingDepositService;
use App\Domain\Pos\Booking\UzPhone;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\ShiftService;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditRedactor;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gift certificates (P6-43, docs/PHASE6_PLAN.md D3, research §5 "bo'nak → sotuv"):
 *
 *  - sell: a non-menu sale at the till (pos.take_payment, the `vouchers` module): the money (cash, card, QR) lands in
 *    the open shift of the terminal (the shift's X/Z counts it from voucher_entries like booking deposits, so it is
 *    never copied into cash_movements and never counted twice), then an ADVANCE receipt is issued for it (one
 *    "deposit" line named as the certificate). Idempotent by the Idempotency-Key (the same key returns the same
 *    voucher; the receipt's own key is the voucher's, so a retry never issues a second one);
 *  - spending it is a tender of a bill (PosTender::VOUCHER, VoucherLedgerService): the sale receipt shows that part as
 *    `prepayment`; a refund of it goes back to the voucher;
 *  - block / unblock: a manager (vouchers.manage) with a reason; audited.
 *
 * Online only: a till without the server cannot sell or spend a voucher (the balance lives here).
 */
class VoucherService
{
    public const MIN_FACE_VALUE = '1000';

    public const MAX_FACE_VALUE = '100000000';

    /** The validity when the seller gives none */
    public const DEFAULT_VALID_MONTHS = 12;

    public const SALE_TENDERS = [PosTender::CASH, PosTender::CARD, PosTender::QR];

    public function __construct(
        protected ShiftService $shifts,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{face_value: string, tender: string, card_type?: string|null, transaction_id?: string|null, reference?: string|null, buyer_name?: string|null, buyer_phone?: string|null, valid_until?: string|null, note?: string|null}  $input
     *
     * @throws PosActionException
     */
    public function sell(User $user, Branch $branch, array $input, ?string $shiftUuid, ?Device $device, ?string $key, ?\Closure $applied = null): Voucher
    {
        [$amount, $tender, $cardType, $transactionId, $reference] = $this->saleInput($input);
        $phone = null;
        if (filled($input['buyer_phone'] ?? null)) {
            $phone = UzPhone::normalize((string) $input['buyer_phone']);
            if ($phone === null) {
                throw PosActionException::invalid('buyer_phone_invalid', "Xaridor telefoni noto'g'ri: +998 XX XXX XX XX.");
            }
        }

        $today = BusinessDay::of($branch);
        $validUntil = filled($input['valid_until'] ?? null)
            ? Carbon::parse((string) $input['valid_until'])->toDateString()
            : Carbon::parse($today)->addMonthsNoOverflow(self::DEFAULT_VALID_MONTHS)->toDateString();
        if ($validUntil < $today) {
            throw PosActionException::invalid('valid_until_invalid', "Amal qilish muddati bugundan oldin bo'lishi mumkin emas.");
        }

        $store = Store::findOrFail($branch->store_id);
        // Built before the money is recorded: a fiscal setup problem (no MXIK for advances) refuses the sale
        $line = $this->line(app(FiscalLineBuilder::class)->forCharge($store, 'deposit', $amount));
        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $branch->id);

        try {
            $voucher = DB::transaction(function () use ($user, $branch, $input, $amount, $tender, $cardType, $transactionId, $reference, $phone, $today, $validUntil, $shift, $device, $key, $applied) {
                $shift = PosShift::lockForMoney((int) $shift->id);
                if (!$shift->isOpen()) {
                    throw PosActionException::conflict('shift_closed', 'Smena yopilgan.');
                }
                if ($key !== null && ($existing = Voucher::where('store_id', $branch->store_id)->where('idempotency_key', $key)->first())) {
                    return $existing;
                }

                $voucher = null;
                for ($attempt = 0; $voucher === null; $attempt++) {
                    $code = VoucherCode::generate();
                    if (Voucher::where('store_id', $branch->store_id)->where('code', $code)->exists()) {
                        if ($attempt > 20) {
                            throw PosActionException::conflict('voucher_code_failed', "Sertifikat kodini yaratib bo'lmadi: qayta urinib ko'ring.");
                        }
                        continue;
                    }
                    $voucher = Voucher::create([
                        'store_id' => $branch->store_id,
                        'branch_id' => $branch->id,
                        'kind' => VoucherKind::GIFT,
                        'code' => $code,
                        'face_value' => $amount->toString(),
                        'balance' => $amount->toString(),
                        'currency' => 'UZS',
                        'valid_from' => $today,
                        'valid_until' => $validUntil,
                        'status' => VoucherStatus::ACTIVE,
                        'sale_tender' => $tender->value,
                        'sale_card_type' => $cardType,
                        'sale_transaction_id' => $transactionId,
                        'sale_reference' => $reference,
                        'sold_shift_id' => $shift->id,
                        'sold_device_id' => $device?->id,
                        'sold_by' => $user->id,
                        'sold_at' => now(),
                        'buyer_name' => filled($input['buyer_name'] ?? null) ? mb_substr(trim((string) $input['buyer_name']), 0, 120) : null,
                        'buyer_phone' => $phone,
                        'note' => filled($input['note'] ?? null) ? mb_substr(trim((string) $input['note']), 0, 500) : null,
                        'idempotency_key' => $key,
                    ]);
                }

                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'store_id' => $voucher->store_id,
                    'branch_id' => $branch->id,
                    'type' => VoucherEntryType::SALE,
                    'business_date' => $today,
                    'amount' => $amount->toString(),
                    'balance_after' => $amount->toString(),
                    'tender' => $tender->value,
                    'shift_id' => $shift->id,
                    'device_id' => $device?->id,
                    'user_id' => $user->id,
                ]);

                $this->audit->record('voucher.sold', $voucher, [], [], array_filter([
                    'code' => VoucherCode::mask($voucher->code),
                    'face_value' => $amount->toString(),
                    'tender' => $tender->value,
                    'valid_until' => $validUntil,
                    'shift' => $shift->uuid,
                    'buyer_phone' => AuditRedactor::maskPhone($phone),
                ], fn ($value) => $value !== null), ['actor' => $user, 'device' => $device]);

                if ($applied) {
                    $applied();
                }

                return $voucher;
            });
        } catch (UniqueConstraintViolationException) {
            // The same Idempotency-Key raced in on another connection: the first one's voucher
            $voucher = Voucher::where('store_id', $branch->store_id)->where('idempotency_key', $key)->firstOrFail();
        }

        if ($voucher->fiscal_receipt_uuid === null) {
            $this->issue($voucher, new FiscalDocument(
                FiscalReceiptType::ADVANCE,
                (int) $voucher->store_id,
                (int) $branch->id,
                $device?->id,
                [$line],
                [new FiscalPayment(BookingDepositService::formOf((string) $voucher->sale_tender), Money::of($voucher->face_value), cardType: $voucher->sale_card_type, transactionId: $voucher->sale_transaction_id)],
                Money::of($voucher->face_value),
                "voucher:{$voucher->uuid}:advance",
                null,
                PosVouchersServiceProvider::FISCAL_SUBJECT,
                (int) $voucher->id,
            ));
        }

        return $voucher->refresh();
    }

    /**
     * A manager stops the voucher (lost, stolen, sold by mistake): it pays nothing until unblocked. Audited.
     */
    public function block(Voucher $voucher, string $reason, User $actor): Voucher
    {
        return $this->setBlocked($voucher, true, $reason, $actor);
    }

    public function unblock(Voucher $voucher, ?string $reason, User $actor): Voucher
    {
        return $this->setBlocked($voucher, false, $reason, $actor);
    }

    private function setBlocked(Voucher $voucher, bool $block, ?string $reason, User $actor): Voucher
    {
        return DB::transaction(function () use ($voucher, $block, $reason, $actor) {
            $voucher = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $blocked = $voucher->status === VoucherStatus::BLOCKED;
            if ($blocked === $block) {
                throw PosActionException::conflict($block ? 'voucher_blocked' : 'voucher_not_blocked', $block ? 'Sertifikat allaqachon bloklangan.' : 'Sertifikat bloklanmagan.');
            }

            $before = ['status' => $voucher->status->value];
            $voucher->forceFill($block ? [
                'status' => VoucherStatus::BLOCKED,
                'blocked_at' => now(),
                'blocked_by' => $actor->id,
                'block_reason' => $reason,
            ] : [
                'status' => Money::of($voucher->balance)->isPositive() ? VoucherStatus::ACTIVE : VoucherStatus::USED,
                'blocked_at' => null,
                'blocked_by' => null,
                'block_reason' => null,
            ])->save();

            VoucherEntry::create([
                'voucher_id' => $voucher->id,
                'store_id' => $voucher->store_id,
                'type' => $block ? VoucherEntryType::BLOCK : VoucherEntryType::UNBLOCK,
                'business_date' => VoucherState::today($voucher),
                'amount' => '0.00',
                'balance_after' => Money::of($voucher->balance)->toString(),
                'user_id' => $actor->id,
                'note' => $reason !== null ? mb_substr($reason, 0, 300) : null,
            ]);

            $this->audit->record($block ? 'voucher.blocked' : 'voucher.unblocked', $voucher, $before, ['status' => $voucher->status->value], array_filter([
                'code' => VoucherCode::mask($voucher->code),
                'reason' => $reason,
                'balance' => Money::of($voucher->balance)->toString(),
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            return $voucher;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: Money, 1: PosTender, 2: string|null, 3: string|null, 4: string|null}
     *
     * @throws PosActionException
     */
    private function saleInput(array $input): array
    {
        $tender = PosTender::tryFrom((string) ($input['tender'] ?? ''));
        if (!in_array($tender, self::SALE_TENDERS, true)) {
            throw PosActionException::invalid('tender_invalid', "Sertifikat naqd, karta yoki QR bilan sotiladi.");
        }
        $amount = Money::of((string) ($input['face_value'] ?? '0'));
        if ($amount->isLessThan(self::MIN_FACE_VALUE) || $amount->isGreaterThan(self::MAX_FACE_VALUE) || !$amount->roundTo(0)->equals($amount)) {
            throw PosActionException::invalid('face_value_invalid', "Sertifikat qiymati 1 000 so'mdan 100 000 000 so'mgacha butun son bo'lsin.");
        }

        $cardType = null;
        $transactionId = null;
        if ($tender === PosTender::CARD) {
            $cardType = strtolower(trim((string) ($input['card_type'] ?? '')));
            $transactionId = trim((string) ($input['transaction_id'] ?? ''));
            if (!in_array($cardType, PaymentService::CARD_TYPES, true)) {
                throw PosActionException::invalid('card_type_required', "Karta turini tanlang (Uzcard, Humo, Visa...).");
            }
            if ($transactionId === '') {
                throw PosActionException::invalid('transaction_id_required', "Bank terminali chekidagi tranzaksiya raqamini kiriting.");
            }
        }
        $reference = $tender === PosTender::QR && filled($input['reference'] ?? null) ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

        return [$amount, $tender, $cardType, $transactionId, $reference];
    }

    /**
     * The advance receipt's one line: the advance service's code, named as the certificate.
     */
    private function line(FiscalLine $line): FiscalLine
    {
        return new FiscalLine(
            name: "Sovg'a sertifikati (avans)",
            mxikCode: $line->mxikCode,
            packageCode: $line->packageCode,
            unit: $line->unit,
            quantity: $line->quantity,
            unitPrice: $line->unitPrice,
            amount: $line->amount,
            vatRate: $line->vatRate,
            vatAmount: $line->vatAmount,
            commissionerTin: $line->commissionerTin,
            markingCode: $line->markingCode,
        );
    }

    /**
     * The money is already recorded: a receipt that cannot be issued now stays visible as fiscal_status `error`
     * (the fiscal queue retries a `pending` one) instead of failing the sale.
     */
    private function issue(Voucher $voucher, FiscalDocument $document): void
    {
        try {
            $result = app(FiscalIssuer::class)->issue($document);
        } catch (Throwable $e) {
            report($e);
            Log::warning("Voucher {$voucher->uuid}: the advance receipt failed: {$e->getMessage()}");
            $voucher->forceFill(['fiscal_status' => 'error'])->save();

            return;
        }

        $voucher->forceFill(['fiscal_receipt_uuid' => $result->receiptUuid, 'fiscal_status' => $result->status])->save();
    }
}
