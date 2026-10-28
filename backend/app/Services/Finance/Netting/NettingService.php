<?php

namespace App\Services\Finance\Netting;

use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Services\Finance\Billing\SubscriptionDebt;
use App\Support\Money;

/**
 * P4-23 (owner decision 28.10.2026, docs/BILLING.md "Netting"): money RIZQIM holds for a store pays the store's debt
 * before it reaches the store — a Click, Payme or keshbek settlement before the wallet, a payout before the card.
 * The oldest subscription invoices first, then the commission debt (SubscriptionDebt's order); each deduction is its
 * own debt ledger row naming what it closed (`subscription_invoice_id` for an invoice), so a refund can reopen
 * exactly those invoices (LedgerService::reverseDeliveredOrder).
 *
 * Every method runs under the caller's lock on the store row (inside its transaction).
 */
class NettingService
{
    public function __construct(
        protected SubscriptionDebt $subscriptionDebt,
    ) {}

    /**
     * Pay as much of the store's debt as $amount covers (never more than the debt). $label names the money in the
     * notes ("Click payment for order #A1B2"), $links are the rows' columns (order_id, reference_id, payment_id,
     * payout_request_id).
     *
     * @param  array<string, int|string|null>  $links
     */
    public function payDebt(Store $store, Money $amount, string $label, array $links = [], ?int $performedBy = null, StoreTransactionType $type = StoreTransactionType::TOPUP): NettingResult
    {
        $take = Money::min($amount, Money::max(Money::zero(), Money::of($store->debt_balance)));
        if (!$take->isPositive()) {
            return NettingResult::none($amount);
        }

        return new NettingResult($amount, $take, $this->book($store, $take, $label, $links, $performedBy, $type));
    }

    /**
     * A payment of the whole $amount (a manual adjustment): the oldest invoices first, the rest the commission debt,
     * and beyond the debt credit (debt below zero), as LedgerService::reduceDebt.
     *
     * @param  array<string, int|string|null>  $links
     */
    public function payment(Store $store, Money $amount, string $label, array $links = [], ?int $performedBy = null, StoreTransactionType $type = StoreTransactionType::ADJUSTMENT): NettingResult
    {
        if (!$amount->isPositive()) {
            return NettingResult::none($amount);
        }

        return new NettingResult($amount, $amount, $this->book($store, $amount, $label, $links, $performedBy, $type));
    }

    /**
     * What payDebt() would take from $amount now, without writing anything (the payout card before approval).
     */
    public function preview(Store $store, Money $amount): NettingResult
    {
        $take = Money::min($amount, Money::max(Money::zero(), Money::of($store->debt_balance)));
        if (!$take->isPositive()) {
            return NettingResult::none($amount);
        }

        $lines = [];
        $left = $take;
        foreach ($this->subscriptionDebt->plan($store, $take) as $share) {
            $lines[] = new NettingLine(NettingLine::INVOICE, $share['amount'], $share['invoice']);
            $left = $left->minus($share['amount']);
        }
        if ($left->isPositive()) {
            $lines[] = new NettingLine(NettingLine::COMMISSION, $left);
        }

        return new NettingResult($amount, $take, $lines);
    }

    /**
     * @param  array<string, int|string|null>  $links
     * @return list<NettingLine>
     */
    private function book(Store $store, Money $take, string $label, array $links, ?int $performedBy, StoreTransactionType $type): array
    {
        $now = now();
        $balance = Money::of($store->debt_balance);
        $lines = [];

        // The invoices are marked paid first (debt_balance not saved yet), then each gets its row
        $shares = $this->subscriptionDebt->pay($store, $take, $now);
        $left = $take;
        foreach ($shares as $share) {
            $invoice = $share['invoice'];
            $row = $this->row($store, $balance, $share['amount'], $type, "{$label}: subscription invoice {$invoice->number} ({$invoice->period})", $links + ['subscription_invoice_id' => $invoice->id], $performedBy);
            $balance = $balance->minus($share['amount']);
            $left = $left->minus($share['amount']);
            $lines[] = new NettingLine(NettingLine::INVOICE, $share['amount'], $invoice, (int) $row->id);
        }

        if ($left->isPositive()) {
            $row = $this->row($store, $balance, $left, $type, "{$label}: commission debt", $links, $performedBy);
            $balance = $balance->minus($left);
            $lines[] = new NettingLine(NettingLine::COMMISSION, $left, null, (int) $row->id);
        }

        $store->forceFill(['debt_balance' => $balance->toString()])->save();
        $this->subscriptionDebt->settleFromCredit($store, $now);

        // Same rule as a top-up: a store blocked by its debt is unblocked once it is back within the credit limit
        if (($store->status->value ?? $store->status) === StoreStatus::PAYMENT_REQUIRED->value && !$store->exceedsCreditLimit($balance)) {
            $store->forceFill(['status' => StoreStatus::ACTIVE->value])->save();
        }

        return $lines;
    }

    /**
     * @param  array<string, int|string|null>  $links
     */
    private function row(Store $store, Money $before, Money $amount, StoreTransactionType $type, string $notes, array $links, ?int $performedBy): StoreTransaction
    {
        return StoreTransaction::create([
            'store_id' => $store->id,
            'type' => $type->value,
            'amount' => $amount->negated()->toString(),
            'balance_before' => $before->toString(),
            'balance_after' => $before->minus($amount)->toString(),
            'performed_by' => $performedBy,
            'notes' => $notes,
        ] + array_intersect_key($links, array_flip(['order_id', 'reference_id', 'payment_id', 'payout_request_id', 'subscription_invoice_id'])));
    }
}
