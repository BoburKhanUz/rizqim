<?php

namespace App\Domain\Pos\Accounts;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Booking\BookingDepositService;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Contracts\PosTender;
use App\Enums\UserRole;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountEntry;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Customer accounts (nasiya, research report R18 and §5 "sotuv → kredit"). The debt (`balance`) moves only here, under
 * the account's row lock, each move an entry with the balance after it:
 *
 *  - charge (CustomerAccountLedger, called by P3-18 when a bill is closed on credit with PosTender::ACCOUNT): refused
 *    for an unknown or blocked account, an account of another store (or of a branch the approving manager does not
 *    work in) and above the credit limit; the approving manager is recorded; one charge per bill (a retry of the same
 *    bill and amount is a no-op). The full SALE receipt is P3-18's, issued when the bill closes;
 *  - repayment: money received (cash, card, QR) with a CREDIT receipt (no fiscal sign or QR; VM 943); never more than
 *    the debt;
 *  - adjustment: a manager's correction with a note (a written-off debt).
 *
 * The owner hears on Telegram about each credit of at least config('pos_bookings.credit_alert_threshold') and about
 * every repayment.
 */
class CustomerAccountService implements CustomerAccountLedger
{
    public const MAX_AMOUNT = '999999999.99';

    public function __construct(
        protected AuditLogger $audit,
        protected PosOwnerAlerts $alerts,
    ) {}

    public function charge(string $accountUuid, Money $amount, string $billUuid, int $approvedByUserId): void
    {
        if (!$amount->isPositive() || $amount->isGreaterThan(self::MAX_AMOUNT)) {
            throw new CustomerAccountException("Nasiya summasi noto'g'ri.", 'invalid_amount');
        }

        $approver = User::find($approvedByUserId);
        if ($approver === null || $approver->store_id === null) {
            throw new CustomerAccountException('Tasdiqlagan menejer topilmadi.', 'approver_unknown');
        }

        $entry = DB::transaction(function () use ($accountUuid, $amount, $billUuid, $approver) {
            $account = CustomerAccount::where('uuid', $accountUuid)->lockForUpdate()->first();

            // Tenant check: the contract carries no store, the approving manager's store is the bill's store
            if ($account === null || (int) $account->store_id !== (int) $approver->store_id) {
                throw new CustomerAccountException('Nasiya hisobi topilmadi.', 'account_not_found', 404);
            }
            if ($account->branch_id !== null && $approver->role !== UserRole::OWNER && $approver->branch_id !== null
                && (int) $approver->branch_id !== (int) $account->branch_id) {
                throw new CustomerAccountException('Bu nasiya hisobi boshqa filialniki.', 'account_other_branch', 403);
            }

            $existing = CustomerAccountEntry::where('customer_account_id', $account->id)
                ->where('type', CustomerAccountEntryType::CHARGE->value)
                ->where('bill_uuid', $billUuid)
                ->first();
            if ($existing !== null) {
                if (Money::of($existing->amount)->equals($amount)) {
                    return null; // the same bill again (a retried close)
                }

                throw new CustomerAccountException('Bu schyot nasiyaga allaqachon boshqa summada yozilgan.', 'already_charged', 409);
            }

            if ($account->is_blocked) {
                throw new CustomerAccountException("\"{$account->name}\" nasiya hisobi bloklangan.", 'account_blocked');
            }

            $balance = Money::of($account->balance)->plus($amount);
            if ($account->credit_limit !== null && $balance->isGreaterThan($account->credit_limit)) {
                $room = Money::max(Money::zero(), Money::of($account->credit_limit)->minus($account->balance));
                throw new CustomerAccountException(
                    "Nasiya limiti yetmaydi: limit {$account->credit_limit} so'm, qarz {$account->balance} so'm, yana ko'pi bilan {$room->toString()} so'm yozish mumkin.",
                    'credit_limit_exceeded',
                    422,
                    ['credit_limit' => $account->credit_limit, 'balance' => $account->balance, 'available' => $room->toString()],
                );
            }

            $actor = auth()->user();
            $entry = $this->record($account, CustomerAccountEntryType::CHARGE, $amount, [
                'bill_uuid' => $billUuid,
                'user_id' => $actor instanceof User ? $actor->id : $approver->id,
                'approved_by' => $approver->id,
                'branch_id' => $approver->branch_id ?? $account->branch_id,
            ]);

            $this->audit->record('customer_account.charged', $account, [], [], [
                'entry_uuid' => $entry->uuid,
                'amount' => $amount->toString(),
                'bill_uuid' => $billUuid,
                'approved_by' => $approver->id,
            ], ['store_id' => (int) $account->store_id, 'branch_id' => $entry->branch_id !== null ? (int) $entry->branch_id : null]);

            return [$account, $entry];
        });

        if ($entry === null) {
            return;
        }

        [$account] = $entry;
        if ($amount->isGreaterThanOrEqualTo((string) config('pos_bookings.credit_alert_threshold', '0'))) {
            $this->alerts->send((int) $account->store_id, '💳 Nasiyaga yozildi: ' . PosOwnerAlerts::sum($amount)
                . "\nMijoz: {$account->name}" . ($account->group_name ? " ({$account->group_name})" : '')
                . "\nJami qarz: " . PosOwnerAlerts::sum($account->balance)
                . "\nTasdiqladi: {$approver->name}");
        }
    }

    /**
     * Money received against the debt, with its CREDIT receipt. Idempotent by `key` (the request's Idempotency-Key).
     */
    public function repay(CustomerAccount $account, Money $amount, PosTender $tender, ?string $cardType, ?string $transactionId, ?string $note, ?string $key, User $actor, ?int $deviceId, ?int $branchId): CustomerAccountEntry
    {
        if (!in_array($tender, [PosTender::CASH, PosTender::CARD, PosTender::QR], true)) {
            throw new CustomerAccountException("Qarz faqat naqd, karta yoki QR bilan to'lanadi.", 'invalid_tender');
        }
        if (!$amount->isPositive() || $amount->isGreaterThan(self::MAX_AMOUNT)) {
            throw new CustomerAccountException("To'lov summasi noto'g'ri.", 'invalid_amount');
        }

        $store = Store::findOrFail($account->store_id);
        // Built before the money is recorded: a fiscal setup problem refuses the repayment
        $line = app(FiscalLineBuilder::class)->forCharge($store, 'credit', $amount);

        $entry = DB::transaction(function () use ($account, $amount, $tender, $cardType, $transactionId, $note, $key, $actor, $deviceId, $branchId) {
            $account = CustomerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($key !== null && ($existing = CustomerAccountEntry::where('customer_account_id', $account->id)->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            if ($amount->isGreaterThan($account->balance)) {
                throw new CustomerAccountException(
                    "To'lov qarzdan ko'p: qarz {$account->balance} so'm.",
                    'more_than_debt',
                    422,
                    ['balance' => $account->balance],
                );
            }

            $entry = $this->record($account, CustomerAccountEntryType::REPAYMENT, $amount->negated(), [
                'tender' => $tender->value,
                'card_type' => $tender === PosTender::CARD ? $cardType : null,
                'transaction_id' => $tender !== PosTender::CASH ? $transactionId : null,
                'note' => $note,
                'idempotency_key' => $key,
                'device_id' => $deviceId,
                'user_id' => $actor->id,
                'branch_id' => $branchId,
            ]);

            $this->audit->record('customer_account.repaid', $account, [], [], [
                'entry_uuid' => $entry->uuid,
                'amount' => $amount->toString(),
                'tender' => $tender->value,
            ], ['actor' => $actor]);

            $this->alerts->send((int) $account->store_id, "✅ Nasiya to'landi: " . PosOwnerAlerts::sum($amount)
                . " ({$tender->value})\nMijoz: {$account->name}\nQolgan qarz: " . PosOwnerAlerts::sum($account->balance)
                . "\nQabul qildi: {$actor->name}");

            return $entry;
        });

        if ($entry->fiscal_receipt_uuid === null) {
            try {
                $result = app(FiscalIssuer::class)->issue(new FiscalDocument(
                    FiscalReceiptType::CREDIT,
                    (int) $account->store_id,
                    $entry->branch_id !== null ? (int) $entry->branch_id : null,
                    $deviceId,
                    [$line],
                    [new FiscalPayment(BookingDepositService::formOf((string) $entry->tender), $amount, cardType: $entry->card_type, transactionId: $entry->transaction_id)],
                    $amount,
                    "customer_account_entry:{$entry->uuid}:credit",
                    null,
                    'customer_account_entry',
                    (int) $entry->id,
                ));
                $entry->forceFill(['fiscal_receipt_uuid' => $result->receiptUuid, 'fiscal_status' => $result->status])->save();
            } catch (Throwable $e) {
                // The money is recorded; the receipt shows as fiscal_status `error` (P3-11 retries a `pending` one)
                report($e);
                Log::warning("Customer account repayment {$entry->uuid}: the credit receipt failed: {$e->getMessage()}");
                $entry->forceFill(['fiscal_status' => 'error'])->save();
            }
        }

        return $entry->refresh();
    }

    /**
     * A manager's correction: `amount` > 0 adds to the debt, < 0 takes from it (a write-off). The debt never goes
     * below zero.
     */
    public function adjust(CustomerAccount $account, Money $amount, string $note, User $actor, ?int $deviceId, ?int $branchId): CustomerAccountEntry
    {
        if ($amount->isZero() || $amount->abs()->isGreaterThan(self::MAX_AMOUNT)) {
            throw new CustomerAccountException("Tuzatish summasi noto'g'ri.", 'invalid_amount');
        }

        return DB::transaction(function () use ($account, $amount, $note, $actor, $deviceId, $branchId) {
            $account = CustomerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if (Money::of($account->balance)->plus($amount)->isNegative()) {
                throw new CustomerAccountException("Tuzatishdan keyin qarz manfiy bo'lib qoladi: qarz {$account->balance} so'm.", 'more_than_debt');
            }

            $entry = $this->record($account, CustomerAccountEntryType::ADJUSTMENT, $amount, [
                'note' => $note,
                'device_id' => $deviceId,
                'user_id' => $actor->id,
                'approved_by' => $actor->id,
                'branch_id' => $branchId,
            ]);

            $this->audit->record('customer_account.adjusted', $account, [], [], [
                'entry_uuid' => $entry->uuid,
                'amount' => $amount->toString(),
                'note' => $note,
            ], ['actor' => $actor]);

            return $entry;
        });
    }

    /**
     * Writes the entry and moves the locked account's balance by `amount`.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function record(CustomerAccount $account, CustomerAccountEntryType $type, Money $amount, array $attributes): CustomerAccountEntry
    {
        $balance = Money::of($account->balance)->plus($amount);
        $account->forceFill(['balance' => $balance->toString()])->save();

        return CustomerAccountEntry::create($attributes + [
            'customer_account_id' => $account->id,
            'store_id' => $account->store_id,
            'type' => $type,
            'amount' => $amount->toString(),
            'balance_after' => $balance->toString(),
        ]);
    }
}
