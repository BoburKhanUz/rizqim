<?php

namespace App\Services\Finance\Payouts;

use App\Enums\AdminPermission;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\Admin\AdminPii;
use App\Services\Finance\Netting\NettingService;
use App\Support\Money;
use App\Support\PayoutView;

/**
 * P4-23: a payout request as the admin app shows it. A list row never carries the full card number; the card does
 * through AdminPii (admin.pii.view, the reveal audited), masked otherwise. Amounts are decimal strings.
 */
class PayoutPresenter
{
    public const ROW = 'array{id: int, store: array{id: int, name: string}|null, amount: string, card: string, status: string, status_label: string, needs_second_approval: bool, first_approval: array{by: array{id: int, name: string}|null, at: string|null, note: string|null}|null, netted_amount: string|null, transferred_amount: string|null, created_at: string|null, processed_at: string|null}';

    public const CARD = 'array{id: int, store: array{id: int, name: string}|null, amount: string, card: string, status: string, status_label: string, needs_second_approval: bool, threshold: string, first_approval: array{by: array{id: int, name: string}|null, at: string|null, note: string|null}|null, notes: string|null, resolution_notes: string|null, processed_by: array{id: int, name: string}|null, netted_amount: string|null, transferred_amount: string|null, created_at: string|null, processed_at: string|null, breakdown: array{accrued: string, netted: string, transfer: string, preview: bool, lines: list<array{kind: string, label: string, amount: string, invoice: array{id: int, uuid: string, number: string, period: string}|null, transaction_id: int|null}>}|null, balances: array{wallet: string, debt: string, subscription_debt: string, commission_debt: string, credit_limit: string}|null, actions: array{can_approve: bool, can_reject: bool, approve_completes: bool}}';

    public function __construct(
        private readonly PayoutService $payouts,
        private readonly NettingService $netting,
        private readonly AdminPii $pii,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function row(PayoutRequest $payout): array
    {
        $payout->loadMissing(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'firstApprover:id,name']);
        $stage = PayoutService::stage($payout);

        return [
            'id' => (int) $payout->id,
            'store' => $payout->store ? ['id' => (int) $payout->store->id, 'name' => (string) $payout->store->name] : null,
            'amount' => Money::of($payout->amount)->toString(),
            'card' => PayoutView::maskCard((string) $payout->card_number),
            'status' => $stage,
            'status_label' => PayoutService::stageLabel($stage),
            'needs_second_approval' => $this->payouts->needsSecondApproval($payout),
            'first_approval' => $payout->first_approved_by !== null ? [
                'by' => $payout->firstApprover ? ['id' => (int) $payout->firstApprover->id, 'name' => (string) $payout->firstApprover->name] : null,
                'at' => $payout->first_approved_at?->toIso8601String(),
                'note' => $payout->first_approval_note,
            ] : null,
            'netted_amount' => $payout->netted_amount !== null ? Money::of($payout->netted_amount)->toString() : null,
            'transferred_amount' => $payout->transferred_amount !== null ? Money::of($payout->transferred_amount)->toString() : null,
            'created_at' => $payout->created_at?->toIso8601String(),
            'processed_at' => $payout->processed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function card(PayoutRequest $payout, User $viewer): array
    {
        $payout->loadMissing('processedBy:id,name');
        $stage = PayoutService::stage($payout);
        $store = Store::withTrashed()->find($payout->store_id);

        return [
            ...$this->row($payout),
            // The approver transfers to this card, so payouts.approve reveals it too while the payout is still open
            'card' => (string) $this->pii->card($viewer, (string) $payout->card_number, 'payout_request', (int) $payout->id,
                in_array($stage, [PayoutService::PENDING, PayoutService::AWAITING_SECOND], true) ? AdminPermission::PAYOUTS_APPROVE : null),
            'threshold' => PayoutService::threshold()->toString(),
            'notes' => PayoutView::maskCardsIn($payout->notes),
            'resolution_notes' => PayoutView::maskCardsIn($payout->resolution_notes),
            'processed_by' => $payout->processedBy ? ['id' => (int) $payout->processedBy->id, 'name' => (string) $payout->processedBy->name] : null,
            'breakdown' => $this->breakdown($payout, $stage, $store),
            'balances' => $store ? [
                'wallet' => Money::of($store->wallet_balance)->toString(),
                'debt' => Money::of($store->debt_balance)->toString(),
                'subscription_debt' => Money::of($store->subscription_debt ?? 0)->toString(),
                'commission_debt' => $store->commissionDebt()->toString(),
                'credit_limit' => Money::of($store->credit_limit)->toString(),
            ] : null,
            'actions' => [
                'can_approve' => $this->payouts->canApprove($payout, $viewer),
                'can_reject' => $this->payouts->canReject($payout, $viewer),
                // Whether this member's approval pays it out now (else it records the first of two approvals)
                'approve_completes' => !$this->payouts->needsSecondApproval($payout) || $stage === PayoutService::AWAITING_SECOND,
            ],
        ];
    }

    /**
     * "Hisoblangan — Ushlangan qarz — O'tkaziladi": what was booked for a completed payout; for an open one what
     * approving it now would net (`preview`); none for a rejected one.
     *
     * @return array<string, mixed>|null
     */
    private function breakdown(PayoutRequest $payout, string $stage, ?Store $store): ?array
    {
        $amount = Money::of($payout->amount);

        if ($stage === PayoutService::COMPLETED) {
            // Completed before P4-23: nothing was netted, all of it went to the card
            $netted = Money::of($payout->netted_amount ?? 0);

            return [
                'accrued' => $amount->toString(),
                'netted' => $netted->toString(),
                'transfer' => Money::of($payout->transferred_amount ?? $amount->minus($netted))->toString(),
                'preview' => false,
                'lines' => array_values((array) ($payout->netting ?? [])),
            ];
        }

        if ($stage === PayoutService::REJECTED || $store === null) {
            return null;
        }

        $preview = $this->netting->preview($store, $amount);

        return [
            'accrued' => $amount->toString(),
            'netted' => $preview->netted->toString(),
            'transfer' => $preview->remainder()->toString(),
            'preview' => true,
            'lines' => $preview->linesArray(),
        ];
    }
}
