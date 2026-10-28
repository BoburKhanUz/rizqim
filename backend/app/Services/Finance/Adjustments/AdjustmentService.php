<?php

namespace App\Services\Finance\Adjustments;

use App\Enums\AdminPermission;
use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Models\FinanceAdjustment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Finance\Billing\SubscriptionDebt;
use App\Services\Finance\Netting\NettingService;
use App\Services\Finance\Payouts\PayoutService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * P4-23: manual adjustments (Moliya → Qo'lda tuzatish; docs/API.md "Moliya"). A member with admin.finance.manage
 * makes one with a reason and, optionally, a document. Under config('finance.four_eyes_threshold') (1 000 000 so'm)
 * it is applied at once; from it, it waits (`pending`) until a DIFFERENT member with admin.finance.manage approves it
 * (owner decision 28.10.2026; a superadmin is no exception), and only then is its ledger row written. Splitting does
 * not get under it: what one member applied alone in the last 24 hours, and what was applied alone on the store,
 * counts with the new amount (needsSecondApproval). Rejecting
 * leaves the ledger alone (the one who made it may withdraw it this way).
 *
 * Every decision runs under the adjustment's row lock; the same decision repeated by the same member answers the
 * same result (replayed). The HTTP layer honours Idempotency-Key.
 */
class AdjustmentService
{
    public function __construct(
        private readonly NettingService $netting,
        private readonly SubscriptionDebt $subscriptionDebt,
        private readonly AdminPermissionService $permissions,
    ) {}

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            FinanceAdjustment::PENDING => 'Ikkinchi tasdiq kutilmoqda',
            FinanceAdjustment::APPLIED => "Qo'llandi",
            FinanceAdjustment::REJECTED => 'Rad etildi',
            default => $status,
        };
    }

    /**
     * @throws AdjustmentException
     */
    public function create(Store $store, User $member, AdjustmentKind $kind, Money $amount, string $reason, ?UploadedFile $document = null): FinanceAdjustment
    {
        if (!$amount->isPositive()) {
            throw new AdjustmentException("Summa 0 dan katta bo'lishi kerak.", 'invalid_amount');
        }

        if ($kind === AdjustmentKind::WALLET_DEBIT) {
            $this->assertWalletCovers($store, $amount);
        }

        $stored = $document !== null ? $this->storeDocument($document) : null;

        try {
            return DB::transaction(function () use ($store, $member, $kind, $amount, $reason, $stored) {
                $needsTwo = $this->needsSecondApproval($store, $member, $amount);
                $adjustment = FinanceAdjustment::create([
                    'store_id' => $store->id,
                    'kind' => $kind->value,
                    'amount' => $amount->toString(),
                    'reason' => mb_substr(trim($reason), 0, 500),
                    'status' => FinanceAdjustment::PENDING,
                    'needs_second_approval' => $needsTwo,
                    'requested_by' => $member->id,
                ] + ($stored ?? []));

                if (!$needsTwo) {
                    $this->apply($adjustment, (int) $member->id);
                }

                return $adjustment->refresh();
            });
        } catch (\Throwable $e) {
            if ($stored !== null) {
                Storage::disk($this->disk())->delete($stored['document_path']);
            }

            throw $e;
        }
    }

    /**
     * @return array{adjustment: FinanceAdjustment, replayed: bool}
     *
     * @throws AdjustmentException
     */
    public function approve(FinanceAdjustment $adjustment, User $member, ?string $note): array
    {
        return DB::transaction(function () use ($adjustment, $member, $note) {
            $adjustment = FinanceAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status === FinanceAdjustment::APPLIED && (int) $adjustment->approved_by === (int) $member->id) {
                return ['adjustment' => $adjustment, 'replayed' => true];
            }
            if (!$adjustment->isPending()) {
                throw new AdjustmentException("Tuzatish allaqachon ko'rib chiqilgan: " . mb_strtolower(self::statusLabel($adjustment->status)) . '.', 'adjustment_decided', 409);
            }
            if ((int) $adjustment->requested_by === (int) $member->id) {
                throw new AdjustmentException("O'zingiz yaratgan tuzatishni tasdiqlay olmaysiz: buni boshqa xodim qiladi.", 'four_eyes', 403);
            }

            $adjustment->forceFill([
                'approved_by' => $member->id,
                'approved_at' => now(),
                'decision_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ])->save();
            $this->apply($adjustment, (int) $member->id);

            return ['adjustment' => $adjustment->refresh(), 'replayed' => false];
        });
    }

    /**
     * @return array{adjustment: FinanceAdjustment, replayed: bool}
     *
     * @throws AdjustmentException
     */
    public function reject(FinanceAdjustment $adjustment, User $member, string $note): array
    {
        return DB::transaction(function () use ($adjustment, $member, $note) {
            $adjustment = FinanceAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status === FinanceAdjustment::REJECTED && (int) $adjustment->rejected_by === (int) $member->id) {
                return ['adjustment' => $adjustment, 'replayed' => true];
            }
            if (!$adjustment->isPending()) {
                throw new AdjustmentException("Tuzatish allaqachon ko'rib chiqilgan: " . mb_strtolower(self::statusLabel($adjustment->status)) . '.', 'adjustment_decided', 409);
            }

            $adjustment->forceFill([
                'status' => FinanceAdjustment::REJECTED,
                'rejected_by' => $member->id,
                'rejected_at' => now(),
                'decision_note' => mb_substr(trim($note), 0, 500),
            ])->save();

            return ['adjustment' => $adjustment->refresh(), 'replayed' => false];
        });
    }

    /**
     * Review W2 (four eyes cannot be dodged by splitting): the amount alone, or with what was applied on one member's
     * word in the last config('finance.four_eyes_window_hours') (24) — by this member on any store, or on this store
     * by any member — reaches the threshold. Adjustments a second member approved, pending and rejected ones do not
     * count; the legacy superadmin panel's manual top-ups (LedgerService::reduceDebt with a member and no reference)
     * do, and that panel asks the same before one (GlobalStoresTable). Under the store's and the member's row locks
     * (store first, like every ledger path): callers run it in a transaction, so two requests at once cannot both pass.
     */
    public function needsSecondApproval(Store $store, User $member, Money $amount): bool
    {
        $threshold = PayoutService::threshold();
        if ($amount->isGreaterThanOrEqualTo($threshold)) {
            return true;
        }

        Store::withTrashed()->whereKey($store->id)->lockForUpdate()->value('id');
        User::whereKey($member->id)->lockForUpdate()->value('id');

        $since = now()->subHours(max(1, (int) config('finance.four_eyes_window_hours', 24)));
        $alone = fn () => FinanceAdjustment::query()->where('status', FinanceAdjustment::APPLIED)
            ->where('needs_second_approval', false)->where('applied_at', '>=', $since);
        $legacy = fn () => StoreTransaction::query()->where('type', StoreTransactionType::TOPUP->value)->whereNotNull('performed_by')
            ->whereNull('reference_id')->whereNull('order_id')->whereNull('payout_request_id')->whereNull('subscription_invoice_id')
            ->where('created_at', '>=', $since);
        $byMember = Money::of((string) $alone()->where('requested_by', $member->id)->sum('amount'))
            ->plus(Money::of((string) $legacy()->where('performed_by', $member->id)->sum('amount'))->negated());
        $onStore = Money::of((string) $alone()->where('store_id', $store->id)->sum('amount'))
            ->plus(Money::of((string) $legacy()->where('store_id', $store->id)->sum('amount'))->negated());

        return $byMember->plus($amount)->isGreaterThanOrEqualTo($threshold)
            || $onStore->plus($amount)->isGreaterThanOrEqualTo($threshold);
    }

    public function canApprove(FinanceAdjustment $adjustment, User $member): bool
    {
        return $adjustment->isPending()
            && (int) $adjustment->requested_by !== (int) $member->id
            && $this->permissions->has($member, AdminPermission::FINANCE_MANAGE);
    }

    public function canReject(FinanceAdjustment $adjustment, User $member): bool
    {
        return $adjustment->isPending() && $this->permissions->has($member, AdminPermission::FINANCE_MANAGE);
    }

    public function disk(): string
    {
        return (string) config('finance.adjustments.disk', 'local');
    }

    /**
     * Write the ledger row(s) and mark it applied. Under the adjustment's row lock.
     *
     * @throws AdjustmentException
     */
    private function apply(FinanceAdjustment $adjustment, int $performedBy): void
    {
        $kind = AdjustmentKind::from($adjustment->kind);
        $amount = Money::of($adjustment->amount);
        $store = Store::withTrashed()->where('id', $adjustment->store_id)->lockForUpdate()->firstOrFail();
        $notes = "Manual adjustment #{$adjustment->id}: {$adjustment->reason}";
        $links = ['reference_id' => (string) $adjustment->uuid, 'performed_by' => $performedBy];
        $update = ['status' => FinanceAdjustment::APPLIED, 'applied_at' => now()];

        switch ($kind) {
            case AdjustmentKind::DEBT_PAYMENT:
                $result = $this->netting->payment($store, $amount, "Manual adjustment #{$adjustment->id} (payment)", ['reference_id' => (string) $adjustment->uuid], $performedBy);
                $update['debt_transaction_id'] = $result->lines[0]->transactionId ?? null;
                break;

            case AdjustmentKind::DEBT_CHARGE:
            case AdjustmentKind::DEBT_CREDIT:
                $before = Money::of($store->debt_balance);
                $change = $kind === AdjustmentKind::DEBT_CHARGE ? $amount : $amount->negated();
                $after = $before->plus($change);
                $row = StoreTransaction::create($links + [
                    'store_id' => $store->id,
                    'type' => StoreTransactionType::ADJUSTMENT->value,
                    'amount' => $change->toString(),
                    'balance_before' => $before->toString(),
                    'balance_after' => $after->toString(),
                    'notes' => $notes,
                ]);
                $store->forceFill(['debt_balance' => $after->toString()])->save();
                // A credit below the subscription part pays the oldest invoices (the part never exceeds the debt)
                $this->subscriptionDebt->settleFromCredit($store, now());
                $this->applyCreditLimit($store, $before, $after);
                $update['debt_transaction_id'] = $row->id;
                break;

            case AdjustmentKind::WALLET_CREDIT:
            case AdjustmentKind::WALLET_DEBIT:
                $before = Money::of($store->wallet_balance);
                $change = $kind === AdjustmentKind::WALLET_CREDIT ? $amount : $amount->negated();
                if ($before->plus($change)->isNegative()) {
                    throw new AdjustmentException("Hamyonda yetarli mablag' yo'q: " . PayoutService::uzs($before) . '.', 'insufficient_wallet', 422, ['wallet_balance' => $before->toString()]);
                }
                $row = StoreWalletTransaction::create($links + [
                    'store_id' => $store->id,
                    'type' => StoreTransactionType::ADJUSTMENT->value,
                    'amount' => $change->toString(),
                    'balance_before' => $before->toString(),
                    'balance_after' => $before->plus($change)->toString(),
                    'notes' => $notes,
                ]);
                $store->forceFill(['wallet_balance' => $before->plus($change)->toString()])->save();
                $update['wallet_transaction_id'] = $row->id;
                break;
        }

        $adjustment->forceFill($update)->save();
    }

    /**
     * The same rules as a commission (an active store over its credit limit is blocked) and a top-up (a blocked one
     * back within it is unblocked).
     */
    private function applyCreditLimit(Store $store, Money $before, Money $after): void
    {
        $status = $store->status->value ?? $store->status;
        if ($after->isGreaterThan($before) && $status === StoreStatus::ACTIVE->value && $store->exceedsCreditLimit($after)) {
            $store->forceFill(['status' => StoreStatus::PAYMENT_REQUIRED->value])->save();
            DB::afterCommit(fn () => event(new \App\Events\StoreBalanceLow($store)));
        } elseif ($after->isLessThan($before) && $status === StoreStatus::PAYMENT_REQUIRED->value && !$store->exceedsCreditLimit($after)) {
            $store->forceFill(['status' => StoreStatus::ACTIVE->value])->save();
        }
    }

    /**
     * @throws AdjustmentException
     */
    private function assertWalletCovers(Store $store, Money $amount): void
    {
        $wallet = Money::of((string) (Store::withTrashed()->whereKey($store->id)->value('wallet_balance') ?? $store->wallet_balance));
        if ($wallet->isLessThan($amount)) {
            throw new AdjustmentException("Hamyonda yetarli mablag' yo'q: " . PayoutService::uzs($wallet) . '.', 'insufficient_wallet', 422, ['wallet_balance' => $wallet->toString()]);
        }
    }

    /**
     * @return array{document_path: string, document_name: string, document_mime: string|null, document_size: int|null}
     */
    private function storeDocument(UploadedFile $file): array
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs(
            (string) config('finance.adjustments.directory', 'finance/adjustments') . '/' . now()->format('Y/m'),
            Str::uuid()->toString() . '.' . $extension,
            ['disk' => $this->disk()],
        );
        if ($path === false) {
            throw new AdjustmentException("Hujjatni saqlab bo'lmadi. Qayta urinib ko'ring.", 'document_failed', 500);
        }

        $name = preg_replace('/[^\pL\pN ._()-]+/u', '_', $file->getClientOriginalName()) ?: "hujjat.{$extension}";

        return [
            'document_path' => $path,
            'document_name' => mb_substr($name, 0, 200),
            'document_mime' => $file->getMimeType(),
            'document_size' => $file->getSize() ?: null,
        ];
    }
}
