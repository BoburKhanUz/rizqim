<?php

namespace App\Services\Finance\Billing;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * P4-21: the subscription part of a store's debt (docs/BILLING.md, "Qarzning ikki qismi"; owner decision 28.10.2026).
 *
 * debt_balance stays the one ledger the owner sees and pays. Within it, stores.subscription_debt is what unpaid
 * subscription invoices make up: the sum of amount - paid_amount over the store's invoices that are not void. The
 * rest (debt_balance - subscription_debt) is the commission debt, the only part the credit limit counts: an unpaid
 * subscription never stops Mini App orders.
 *
 * - A payment (an online or manual top-up, keshbek, payout netting) pays the oldest invoices first, then the commission
 *   debt; an invoice paid in full becomes `paid` and the subscription leaves past_due / locked at once.
 * - A charge joins the subscription part; credit the store already has (debt below zero) pays it at once.
 * - The subscription part never exceeds the debt: whenever it would (a commission refund, a voided invoice that was
 *   partly paid), the credit pays the oldest invoices.
 *
 * Every method runs under the caller's lock on the store row and saves the column itself.
 *
 * P4-23 (netting, docs/BILLING.md "Netting"): pay() tells which invoices a payment paid, so each gets its own ledger
 * row; reopen() makes an invoice unpaid again when the money that paid it is taken back (a refunded order).
 */
class SubscriptionDebt
{
    public function __construct(
        protected SubscriptionStatusResolver $statuses,
    ) {}

    /**
     * An invoice was just posted to the debt ledger.
     */
    public function charged(Store $store, CarbonInterface $now): void
    {
        $this->recompute($store);
        $this->settleFromCredit($store, $now);
    }

    /**
     * The debt went down by a payment of $amount (debt_balance already saved): it pays the oldest invoices first.
     * Returns how much of it went to invoices.
     */
    public function paid(Store $store, Money $amount, CarbonInterface $now): Money
    {
        $this->recompute($store);
        $allocated = self::total($this->allocate($store, Money::min($amount, $this->part($store)), $now));
        $this->settleFromCredit($store, $now);

        return $allocated;
    }

    /**
     * P4-23 (netting): $amount of the store's money is about to pay its debt (debt_balance NOT saved yet): the oldest
     * invoices first, as paid() does. Returns which invoices it paid and how much of each, so the caller books one
     * ledger row per invoice; the rest of $amount is the commission debt's. The caller saves debt_balance after.
     *
     * @return list<array{invoice: SubscriptionInvoice, amount: Money}>
     */
    public function pay(Store $store, Money $amount, CarbonInterface $now): array
    {
        $this->recompute($store);

        return $this->allocate($store, Money::min($amount, $this->part($store)), $now);
    }

    /**
     * P4-23: what pay() would pay now, without writing anything (the payout card's preview).
     *
     * @return list<array{invoice: SubscriptionInvoice, amount: Money}>
     */
    public function plan(Store $store, Money $amount): array
    {
        $left = Money::min($amount, $this->outstanding((int) $store->id));
        $shares = [];
        foreach ($this->openInvoices((int) $store->id)->get() as $invoice) {
            if (!$left->isPositive()) {
                break;
            }
            $take = Money::min($left, $invoice->outstanding());
            $shares[] = ['invoice' => $invoice, 'amount' => $take];
            $left = $left->minus($take);
        }

        return $shares;
    }

    /**
     * P4-23: money that had paid this invoice was taken back (a refunded order whose settlement paid it): up to what
     * was paid of it becomes unpaid again, the subscription part grows and the subscription's status is worked out
     * again (past_due / locked when it is late). Runs under the caller's lock on the store row, debt_balance already
     * raised by $amount. Returns what was reopened (the rest stays commission debt).
     *
     * - It goes back to `issued` only when the ledger had paid it in full (paid_amount ≥ amount before); one marked
     *   "To'landi" by hand (markPaid) stays paid, only its paid_amount drops (the subscription part shows the rest).
     * - A void invoice is not reopened. When it was voided after it was paid, its money became credit that paid the
     *   store's other invoices: those are reopened instead, the most recently paid first (since the void), never more
     *   than the debt holds beyond the subscription part, so the share never turns into commission debt.
     */
    public function reopen(Store $store, int $invoiceId, Money $amount, CarbonInterface $now): Money
    {
        $invoice = SubscriptionInvoice::whereKey($invoiceId)->where('store_id', $store->id)->lockForUpdate()->first();
        if ($invoice === null || !$amount->isPositive()) {
            return Money::zero();
        }

        if ($invoice->status === SubscriptionInvoiceStatus::VOID) {
            $reopened = $this->reopenInsteadOfVoid($store, $invoice, $amount);
        } else {
            $reopened = $this->unpay($invoice, $amount);
        }
        if (!$reopened->isPositive()) {
            return Money::zero();
        }

        $this->recompute($store);
        $this->statuses->refresh((int) $store->id, $now);

        return $reopened;
    }

    /**
     * Take up to $amount off what was paid of a (not void) invoice. Returns what was taken.
     */
    protected function unpay(SubscriptionInvoice $invoice, Money $amount): Money
    {
        $paidBefore = Money::of($invoice->paid_amount ?? 0);
        $take = Money::min($amount, $paidBefore);
        if (!$take->isPositive()) {
            return Money::zero();
        }

        $update = ['paid_amount' => $paidBefore->minus($take)->toString()];
        // Paid by the ledger (in full), not by a manual "To'landi" on a partly paid one
        if ($invoice->status === SubscriptionInvoiceStatus::PAID && $paidBefore->isGreaterThanOrEqualTo($invoice->total())) {
            $update['status'] = SubscriptionInvoiceStatus::ISSUED;
            $update['paid_at'] = null;
        }
        $invoice->update($update);

        return $take;
    }

    /**
     * The money that had paid $void came back as debt: reopen the invoices its void credit paid (paid since the void,
     * most recently first), up to what the debt holds beyond the subscription part.
     */
    protected function reopenInsteadOfVoid(Store $store, SubscriptionInvoice $void, Money $amount): Money
    {
        $this->recompute($store);
        $room = Money::max(Money::zero(), Money::of($store->debt_balance))->minus($this->part($store));
        $left = Money::min($amount, $room);
        if (!$left->isPositive()) {
            return Money::zero();
        }

        $candidates = SubscriptionInvoice::where('store_id', $store->id)
            ->where('status', '!=', SubscriptionInvoiceStatus::VOID->value)
            ->where('paid_amount', '>', 0)
            ->when($void->voided_at !== null, fn ($query) => $query->where('updated_at', '>=', $void->voided_at))
            ->orderByDesc('updated_at')
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $reopened = Money::zero();
        foreach ($candidates as $invoice) {
            if (!$left->isPositive()) {
                break;
            }
            $take = $this->unpay($invoice, $left);
            $reopened = $reopened->plus($take);
            $left = $left->minus($take);
        }

        return $reopened;
    }

    /**
     * An invoice was voided (its status is already void): what was unpaid of it leaves the subscription part.
     */
    public function voided(Store $store, CarbonInterface $now): void
    {
        $this->recompute($store);
        $this->settleFromCredit($store, $now);
    }

    /**
     * Any change of debt_balance: the subscription part is capped at the debt, the credit paying invoices.
     */
    public function settleFromCredit(Store $store, CarbonInterface $now): Money
    {
        $excess = $this->part($store)->minus(Money::max(Money::zero(), Money::of($store->debt_balance)));

        return $excess->isPositive() ? self::total($this->allocate($store, $excess, $now)) : Money::zero();
    }

    /**
     * The subscription part as the invoices say (amount - paid_amount of those not void).
     */
    public function outstanding(int $storeId): Money
    {
        $row = SubscriptionInvoice::where('store_id', $storeId)
            ->where('status', '!=', SubscriptionInvoiceStatus::VOID->value)
            ->whereColumn('amount', '>', 'paid_amount')
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) AS open')
            ->first();

        return Money::of((string) ($row?->getAttribute('open') ?? '0'));
    }

    /**
     * Rebuild a store's split from its invoices and debt (billing:migrate-plans): the subscription part is what its
     * invoices leave unpaid, capped at the debt; the credit pays the oldest. Returns the part before and after.
     *
     * @return array{before: string, after: string, commission_debt: string, invoices_paid: int}
     */
    public function rebuild(Store $store, CarbonInterface $now): array
    {
        $before = $this->part($store)->toString();
        $paidBefore = SubscriptionInvoice::where('store_id', $store->id)->where('status', SubscriptionInvoiceStatus::PAID->value)->count();

        $this->recompute($store);
        $this->settleFromCredit($store, $now);

        return [
            'before' => $before,
            'after' => $this->part($store)->toString(),
            'commission_debt' => $store->commissionDebt()->toString(),
            'invoices_paid' => SubscriptionInvoice::where('store_id', $store->id)->where('status', SubscriptionInvoiceStatus::PAID->value)->count() - $paidBefore,
        ];
    }

    protected function part(Store $store): Money
    {
        return Money::of($store->getAttribute('subscription_debt') ?? 0);
    }

    protected function recompute(Store $store): void
    {
        $open = $this->outstanding((int) $store->id);
        if (!$open->equals($this->part($store))) {
            $store->forceFill(['subscription_debt' => $open->toString()])->save();
        }
    }

    /**
     * The store's invoices with something unpaid, oldest first (due date, then month): the order every payment pays.
     *
     * @return \Illuminate\Database\Eloquent\Builder<SubscriptionInvoice>
     */
    protected function openInvoices(int $storeId): \Illuminate\Database\Eloquent\Builder
    {
        return SubscriptionInvoice::where('store_id', $storeId)
            ->where('status', '!=', SubscriptionInvoiceStatus::VOID->value)
            ->whereColumn('amount', '>', 'paid_amount')
            ->orderBy('due_date')
            ->orderBy('period')
            ->orderBy('id');
    }

    /**
     * @param  list<array{invoice: SubscriptionInvoice, amount: Money}>  $shares
     */
    protected static function total(array $shares): Money
    {
        return Money::sum(...array_map(fn (array $share) => $share['amount'], $shares));
    }

    /**
     * Pay up to $amount of the store's oldest unpaid invoices (due date, then month). Returns what each was paid.
     *
     * @return list<array{invoice: SubscriptionInvoice, amount: Money}>
     */
    protected function allocate(Store $store, Money $amount, CarbonInterface $now): array
    {
        if (!$amount->isPositive()) {
            return [];
        }

        $left = $amount;
        $shares = [];
        $invoices = $this->openInvoices((int) $store->id)->lockForUpdate()->get();

        foreach ($invoices as $invoice) {
            if (!$left->isPositive()) {
                break;
            }

            $take = Money::min($left, $invoice->outstanding());
            $paid = Money::of($invoice->paid_amount)->plus($take);
            $update = ['paid_amount' => $paid->toString()];
            if ($paid->isGreaterThanOrEqualTo($invoice->total()) && $invoice->status === SubscriptionInvoiceStatus::ISSUED) {
                $update['status'] = SubscriptionInvoiceStatus::PAID;
                $update['paid_at'] = Carbon::instance($now);
            }
            $invoice->update($update);
            $shares[] = ['invoice' => $invoice, 'amount' => $take];
            $left = $left->minus($take);
        }

        $used = $amount->minus($left);
        if ($used->isPositive()) {
            $store->forceFill(['subscription_debt' => Money::max(Money::zero(), $this->part($store)->minus($used))->toString()])->save();
            $this->statuses->refresh((int) $store->id, $now);
        }

        return $shares;
    }
}
