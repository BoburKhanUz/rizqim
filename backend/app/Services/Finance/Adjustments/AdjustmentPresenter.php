<?php

namespace App\Services\Finance\Adjustments;

use App\Models\FinanceAdjustment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Support\Money;

/**
 * P4-23: a manual adjustment as the admin app shows it; amounts are decimal strings, `signed_amount` with the sign
 * of the ledger change (+ debt up / wallet up). `transactions` — the ledger rows it wrote (a payment: one per invoice
 * and one for the commission debt).
 */
class AdjustmentPresenter
{
    public const SHAPE = 'array{id: int, uuid: string, store: array{id: int, name: string}|null, kind: string, kind_label: string, ledger: string, amount: string, signed_amount: string, reason: string, status: string, status_label: string, needs_second_approval: bool, requested_by: array{id: int, name: string}|null, requested_at: string|null, approved_by: array{id: int, name: string}|null, approved_at: string|null, rejected_by: array{id: int, name: string}|null, rejected_at: string|null, decision_note: string|null, applied_at: string|null, document: array{name: string, mime: string|null, size: int|null}|null, transactions: list<array{ledger: string, id: int, type: string, amount: string, balance_before: string, balance_after: string, notes: string|null, subscription_invoice_id: int|null}>, mine: bool, can_approve: bool, can_reject: bool}';

    public function __construct(
        private readonly AdjustmentService $adjustments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(FinanceAdjustment $adjustment, User $viewer, bool $withTransactions = false): array
    {
        $adjustment->loadMissing([
            'store' => fn ($store) => $store->withTrashed()->select(['id', 'name']),
            'requester:id,name', 'approver:id,name', 'rejecter:id,name',
        ]);
        $kind = AdjustmentKind::tryFrom($adjustment->kind);
        $amount = Money::of($adjustment->amount);
        $person = fn (?User $user) => $user ? ['id' => (int) $user->id, 'name' => (string) $user->name] : null;

        return [
            'id' => (int) $adjustment->id,
            'uuid' => (string) $adjustment->uuid,
            'store' => $adjustment->store ? ['id' => (int) $adjustment->store->id, 'name' => (string) $adjustment->store->name] : null,
            'kind' => $adjustment->kind,
            'kind_label' => $kind?->label() ?? $adjustment->kind,
            'ledger' => $kind?->ledger() ?? 'debt',
            'amount' => $amount->toString(),
            'signed_amount' => (($kind?->sign() ?? 1) > 0 ? $amount : $amount->negated())->toString(),
            'reason' => $adjustment->reason,
            'status' => $adjustment->status,
            'status_label' => AdjustmentService::statusLabel($adjustment->status),
            'needs_second_approval' => $adjustment->needs_second_approval,
            'requested_by' => $person($adjustment->requester),
            'requested_at' => $adjustment->created_at?->toIso8601String(),
            'approved_by' => $person($adjustment->approver),
            'approved_at' => $adjustment->approved_at?->toIso8601String(),
            'rejected_by' => $person($adjustment->rejecter),
            'rejected_at' => $adjustment->rejected_at?->toIso8601String(),
            'decision_note' => $adjustment->decision_note,
            'applied_at' => $adjustment->applied_at?->toIso8601String(),
            'document' => $adjustment->document_path !== null ? [
                'name' => (string) $adjustment->document_name,
                'mime' => $adjustment->document_mime,
                'size' => $adjustment->document_size,
            ] : null,
            'transactions' => $withTransactions ? $this->transactions($adjustment) : [],
            'mine' => (int) $adjustment->requested_by === (int) $viewer->id,
            'can_approve' => $this->adjustments->canApprove($adjustment, $viewer),
            'can_reject' => $this->adjustments->canReject($adjustment, $viewer),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function transactions(FinanceAdjustment $adjustment): array
    {
        if ($adjustment->status !== FinanceAdjustment::APPLIED) {
            return [];
        }

        $row = fn (string $ledger) => fn (StoreTransaction|StoreWalletTransaction $transaction) => [
            'ledger' => $ledger,
            'id' => (int) $transaction->id,
            'type' => $transaction->type instanceof \BackedEnum ? $transaction->type->value : (string) $transaction->type,
            'amount' => Money::of($transaction->amount)->toString(),
            'balance_before' => Money::of($transaction->balance_before)->toString(),
            'balance_after' => Money::of($transaction->balance_after)->toString(),
            'notes' => $transaction->notes,
            'subscription_invoice_id' => $transaction->getAttribute('subscription_invoice_id') !== null ? (int) $transaction->getAttribute('subscription_invoice_id') : null,
        ];

        return [
            ...StoreTransaction::where('store_id', $adjustment->store_id)->where('reference_id', $adjustment->uuid)->orderBy('id')->get()->map($row('debt'))->all(),
            ...StoreWalletTransaction::where('store_id', $adjustment->store_id)->where('reference_id', $adjustment->uuid)->orderBy('id')->get()->map($row('wallet'))->all(),
        ];
    }
}
