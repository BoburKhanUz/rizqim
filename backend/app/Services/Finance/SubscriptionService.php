<?php

namespace App\Services\Finance;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P1-23: store subscriptions and their monthly invoices (docs/BILLING.md).
 *
 * - A billing period is a calendar month in config('billing.timezone'); a subscription's first period runs from the
 *   moment it starts to the end of that month. No proration: a period is billed in full.
 * - When a period ends, it is billed under the subscription's plan: fee = base + max(0, terminals - included) ×
 *   per_terminal, terminals being TerminalCounter::maxConcurrent over the period. A period the trial touches at all,
 *   and a zero fee, create no invoice. The invoice (one per store and month) is posted to the debt ledger.
 * - A plan change waits for the end of the month (next_tariff_plan_id) unless made immediately: then the current
 *   row ends now, the new plan's row runs to the same month end and bills the whole month alone. Only a store's first
 *   subscription gets the plan's trial; a change keeps whatever is left of the current trial.
 * - Cancelling immediately ends the subscription without billing the month; at period end bills it, then ends it.
 */
class SubscriptionService
{
    public function __construct(
        protected LedgerService $ledger,
        protected TerminalCounter $terminals,
        protected AuditLogger $audit,
    ) {}

    public function timezone(): string
    {
        return (string) config('billing.timezone', 'Asia/Tashkent');
    }

    /**
     * The start of the billing month after the one $at falls in, in the app timezone (what the columns hold).
     */
    public function nextMonthStart(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at)->setTimezone($this->timezone())->startOfMonth()->addMonthNoOverflow()
            ->setTimezone((string) config('app.timezone'));
    }

    /**
     * "2026-10": the billing month a period starting at $start belongs to.
     */
    public function periodKey(CarbonInterface $start): string
    {
        return Carbon::instance($start)->setTimezone($this->timezone())->format('Y-m');
    }

    public function current(Store $store): ?StoreSubscription
    {
        return StoreSubscription::query()->current()->where('store_id', $store->id)->with(['plan', 'nextPlan'])->latest('id')->first();
    }

    /**
     * Put a store on a plan. A store without a subscription starts now (with the plan's trial if it never had one);
     * otherwise the change waits for the end of the month, or applies now when $immediately. Choosing the current
     * plan again drops a pending change or cancellation.
     */
    public function assignPlan(Store $store, TariffPlan $plan, bool $immediately = false, ?User $actor = null, ?CarbonInterface $now = null): StoreSubscription
    {
        if (!$plan->is_active) {
            throw new \DomainException("Bu tarif o'chirilgan, uni biriktirib bo'lmaydi.");
        }

        $now = $this->now($now);

        return DB::transaction(function () use ($store, $plan, $immediately, $actor, $now) {
            // One writer per store, so two assignments cannot both find no current subscription
            Store::whereKey($store->id)->lockForUpdate()->firstOrFail();
            $current = StoreSubscription::query()->current()->where('store_id', $store->id)->lockForUpdate()->first();

            if (!$current) {
                $isFirst = !StoreSubscription::where('store_id', $store->id)->exists();
                $subscription = $this->open($store->id, $plan, $now, $isFirst && $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null, $this->nextMonthStart($now), $actor, $now);
                $this->audit->record('subscription.started', $subscription, [], ['plan' => $plan->code], [], ['actor' => $actor, 'store_id' => $store->id]);

                return $subscription;
            }

            if ($current->tariff_plan_id === $plan->id) {
                $current->update(['next_tariff_plan_id' => null, 'cancel_at_period_end' => false]);

                return $current;
            }

            $from = $current->plan()->value('code');

            if (!$immediately) {
                $current->update(['next_tariff_plan_id' => $plan->id, 'cancel_at_period_end' => false]);
                $this->audit->record('subscription.plan_change_scheduled', $current, ['plan' => $from], ['plan' => $plan->code], ['from' => $current->current_period_end->toIso8601String()], ['actor' => $actor, 'store_id' => $store->id]);

                return $current;
            }

            $trialEndsAt = $current->trial_ends_at?->greaterThan($now) ? $current->trial_ends_at : null;
            $periodEnd = $current->current_period_end;
            $current->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $now, 'next_tariff_plan_id' => null, 'cancel_at_period_end' => false]);

            $subscription = $this->open($store->id, $plan, $now, $trialEndsAt, $periodEnd, $actor, $now);
            $this->audit->record('subscription.plan_changed', $subscription, ['plan' => $from], ['plan' => $plan->code], [], ['actor' => $actor, 'store_id' => $store->id]);

            return $subscription;
        });
    }

    /**
     * End a subscription: now (the month is not billed) or when its month ends (billed, then ended).
     */
    public function cancel(StoreSubscription $subscription, bool $immediately, ?User $actor = null, ?CarbonInterface $now = null): StoreSubscription
    {
        $now = $this->now($now);

        return DB::transaction(function () use ($subscription, $immediately, $actor, $now) {
            $subscription = StoreSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if (!$subscription->isCurrent()) {
                return $subscription;
            }

            $subscription->update($immediately
                ? ['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $now, 'next_tariff_plan_id' => null, 'cancel_at_period_end' => false]
                : ['cancel_at_period_end' => true, 'next_tariff_plan_id' => null]);

            $this->audit->record('subscription.cancelled', $subscription, [], [], ['immediately' => $immediately], ['actor' => $actor]);

            return $subscription;
        });
    }

    /**
     * The scheduled run: bill every period that has ended (catching up months a missed run left behind), then bring
     * invoice and subscription statuses up to date. Running it again changes nothing. Returns the invoices created.
     */
    public function run(?CarbonInterface $now = null): int
    {
        $now = $this->now($now);
        $created = $this->closeEndedPeriods($now);
        $this->syncStatuses($now);

        return $created;
    }

    public function closeEndedPeriods(?CarbonInterface $now = null): int
    {
        $now = $this->now($now);
        $created = 0;

        $ids = StoreSubscription::query()->current()->where('current_period_end', '<=', $now)->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            try {
                $created += DB::transaction(function () use ($id, $now) {
                    $subscription = StoreSubscription::whereKey($id)->lockForUpdate()->first();
                    $count = 0;

                    while ($subscription && $subscription->isCurrent() && $subscription->current_period_end->lessThanOrEqualTo($now)) {
                        [$invoice, $subscription] = $this->closePeriod($subscription, $now);
                        $count += $invoice?->wasRecentlyCreated ? 1 : 0;
                    }

                    return $count;
                });
            } catch (Throwable $e) {
                // One store's failure never stops the others; the next run tries it again
                Log::error("Subscription {$id}: billing the ended period failed", ['error' => $e->getMessage()]);
                report($e);
            }
        }

        return $created;
    }

    /**
     * Mark paid the issued invoices of stores that owe nothing, and set each current subscription's status
     * (past_due when an issued invoice is past its due date, trialing during the trial, otherwise active).
     */
    public function syncStatuses(?CarbonInterface $now = null): void
    {
        $now = $this->now($now);

        SubscriptionInvoice::where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->whereIn('store_id', Store::withTrashed()->where('debt_balance', '<=', 0)->select('id'))
            ->update(['status' => SubscriptionInvoiceStatus::PAID->value, 'paid_at' => $now, 'updated_at' => $now]);

        foreach (StoreSubscription::query()->current()->cursor() as $subscription) {
            $status = $this->statusFor($subscription, $now);
            if ($status !== $subscription->status) {
                $subscription->update(['status' => $status]);
            }
        }
    }

    /**
     * fee = base + max(0, terminals - included) × per_terminal, with the lines an invoice shows.
     *
     * @return array{amount: Money, extra_terminals: int, lines: list<array{type: string, label: string, quantity: int, unit_price: string, amount: string}>}
     */
    public function quote(TariffPlan $plan, int $terminals): array
    {
        $base = $plan->baseFee();
        $perTerminal = $plan->feePerTerminal();
        $included = max(0, $plan->included_terminals);
        $extra = max(0, $terminals - $included);
        $terminalFee = $perTerminal->times($extra);
        $lines = [];

        if ($base->isPositive()) {
            $lines[] = ['type' => 'base', 'label' => "Oylik asosiy to'lov", 'quantity' => 1, 'unit_price' => $base->toString(), 'amount' => $base->toString()];
        }
        if ($perTerminal->isPositive()) {
            $lines[] = [
                'type' => 'terminals',
                'label' => "Terminallar: {$terminals} ta, {$included} tasi tarifga kiradi",
                'quantity' => $extra,
                'unit_price' => $perTerminal->toString(),
                'amount' => $terminalFee->toString(),
            ];
        }

        return ['amount' => $base->plus($terminalFee), 'extra_terminals' => $extra, 'lines' => $lines];
    }

    /**
     * What the owner sees: the current plan and period, the terminals counted so far and the invoice the period
     * would get if it ended now.
     *
     * @return array<string, mixed>
     */
    public function overview(Store $store, ?CarbonInterface $now = null): array
    {
        $now = $this->now($now);
        $subscription = $this->current($store);
        $activeNow = $this->terminals->activeNow($store->id);

        if (!$subscription) {
            return [
                'subscription' => null,
                'commission_rate' => (float) $store->commissionRate(),
                'terminals' => ['active_now' => $activeNow, 'counted' => null],
                'next_invoice' => null,
            ];
        }

        $plan = $subscription->plan;
        $counted = $this->terminals->maxConcurrent($store->id, $subscription->current_period_start, Carbon::instance($now)->min($subscription->current_period_end));
        // An active terminal keeps counting until the period ends
        $counted = max($counted, $activeNow);
        $inTrial = $this->trialCovers($subscription, $subscription->current_period_start);
        $quote = $this->quote($plan, $counted);
        $amount = $inTrial ? Money::zero() : $quote['amount'];

        return [
            'subscription' => [
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'plan' => $this->planArray($plan),
                'started_at' => $subscription->started_at->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period' => [
                    'period' => $this->periodKey($subscription->current_period_start),
                    'start' => $subscription->current_period_start->toIso8601String(),
                    'end' => $subscription->current_period_end->toIso8601String(),
                ],
                'next_plan' => $subscription->nextPlan ? $this->planArray($subscription->nextPlan) : null,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ],
            'commission_rate' => (float) $store->commissionRate(),
            'terminals' => ['active_now' => $activeNow, 'counted' => $counted],
            'next_invoice' => [
                'period' => $this->periodKey($subscription->current_period_start),
                'issued_on' => Carbon::instance($subscription->current_period_end)->setTimezone($this->timezone())->toDateString(),
                'trial' => $inTrial,
                'amount' => $amount->toFloat(),
                'lines' => $inTrial ? [] : array_map(fn (array $line) => [
                    'type' => $line['type'],
                    'label' => $line['label'],
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::of($line['unit_price'])->toFloat(),
                    'amount' => Money::of($line['amount'])->toFloat(),
                ], $quote['lines']),
            ],
        ];
    }

    /**
     * Void an invoice: its ledger charge is reversed (debt down) and it no longer counts as income.
     */
    public function voidInvoice(SubscriptionInvoice $invoice, ?User $actor = null, ?string $reason = null, ?CarbonInterface $now = null): SubscriptionInvoice
    {
        $now = $this->now($now);

        $invoice = DB::transaction(function () use ($invoice, $actor, $reason, $now) {
            $invoice = SubscriptionInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status === SubscriptionInvoiceStatus::VOID) {
                return $invoice;
            }

            $reversal = $this->ledger->reverseSubscriptionCharge($invoice, $actor?->id, $reason);
            $before = $invoice->status->value;
            $invoice->update([
                'status' => SubscriptionInvoiceStatus::VOID,
                'voided_at' => $now,
                'voided_by' => $actor?->id,
                'void_reason' => $reason,
                'void_transaction_id' => $reversal?->id,
            ]);

            $this->audit->record('subscription_invoice.voided', $invoice, ['status' => $before], ['status' => 'void'], ['number' => $invoice->number, 'reason' => $reason], ['actor' => $actor]);

            return $invoice;
        });

        $this->syncStoreStatus($invoice->store_id, $now);

        return $invoice;
    }

    /**
     * Mark an issued invoice paid by hand (money received outside the top-up flow). The ledger is not touched: the
     * payment itself is booked as a top-up, which reduces the debt.
     */
    public function markPaid(SubscriptionInvoice $invoice, ?User $actor = null, ?CarbonInterface $now = null): SubscriptionInvoice
    {
        $now = $this->now($now);

        $updated = SubscriptionInvoice::whereKey($invoice->id)->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->update(['status' => SubscriptionInvoiceStatus::PAID->value, 'paid_at' => $now, 'updated_at' => $now]);

        $invoice->refresh();
        if ($updated) {
            $this->audit->record('subscription_invoice.paid', $invoice, ['status' => 'issued'], ['status' => 'paid'], ['number' => $invoice->number], ['actor' => $actor]);
            $this->syncStoreStatus($invoice->store_id, $now);
        }

        return $invoice;
    }

    /**
     * @return array{code: string, name: string, description: string|null, summary: string, commission_percent: float|null, monthly_base_fee: float, monthly_fee_per_terminal: float, included_terminals: int, trial_days: int}
     */
    public function planArray(TariffPlan $plan): array
    {
        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'summary' => $plan->summary(),
            'commission_percent' => $plan->commission_percent !== null ? (float) $plan->commission_percent : null,
            'monthly_base_fee' => $plan->baseFee()->toFloat(),
            'monthly_fee_per_terminal' => $plan->feePerTerminal()->toFloat(),
            'included_terminals' => $plan->included_terminals,
            'trial_days' => $plan->trial_days,
        ];
    }

    /**
     * Bill the subscription's current period (it has ended) and move it on: to the next month, to the plan chosen
     * for next month (a new row), or to its end. Returns the period's invoice (null when none is due) and the
     * subscription that is current afterwards.
     *
     * @return array{0: SubscriptionInvoice|null, 1: StoreSubscription|null}
     */
    protected function closePeriod(StoreSubscription $subscription, CarbonInterface $now): array
    {
        $from = $subscription->current_period_start;
        $to = $subscription->current_period_end;
        $invoice = null;

        if (!$this->trialCovers($subscription, $from)) {
            $plan = $subscription->plan()->firstOrFail();
            $terminals = $this->terminals->maxConcurrent($subscription->store_id, $from, $to);
            $quote = $this->quote($plan, $terminals);

            if ($quote['amount']->isPositive()) {
                $invoice = $this->issue($subscription, $plan, $terminals, $quote, $now);
            }
        }

        if ($subscription->cancel_at_period_end) {
            $subscription->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $to]);

            return [$invoice, null];
        }

        if ($subscription->next_tariff_plan_id !== null && ($next = TariffPlan::find($subscription->next_tariff_plan_id))) {
            $subscription->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $to]);
            $trialEndsAt = $subscription->trial_ends_at?->greaterThan($to) ? $subscription->trial_ends_at : null;

            return [$invoice, $this->open($subscription->store_id, $next, $to, $trialEndsAt, $this->nextMonthStart($to), null, $now)];
        }

        $subscription->current_period_start = $to;
        $subscription->current_period_end = $this->nextMonthStart($to);
        $subscription->next_tariff_plan_id = null;
        $subscription->status = $this->statusFor($subscription, $now);
        $subscription->save();

        return [$invoice, $subscription];
    }

    /**
     * The period's invoice, created and charged at most once (unique store + month); an existing one is returned,
     * its charge made good if it is missing.
     *
     * @param  array{amount: Money, extra_terminals: int, lines: list<array<string, mixed>>}  $quote
     */
    protected function issue(StoreSubscription $subscription, TariffPlan $plan, int $terminals, array $quote, CarbonInterface $now): SubscriptionInvoice
    {
        $period = $this->periodKey($subscription->current_period_start);
        $invoice = SubscriptionInvoice::where('store_id', $subscription->store_id)->where('period', $period)->lockForUpdate()->first();

        if (!$invoice) {
            $invoice = SubscriptionInvoice::create([
                'number' => sprintf('%s-%s-%06d', config('billing.invoice_prefix', 'OB'), str_replace('-', '', $period), $subscription->store_id),
                'store_id' => $subscription->store_id,
                'store_subscription_id' => $subscription->id,
                'tariff_plan_id' => $plan->id,
                'period' => $period,
                'period_start' => $subscription->current_period_start,
                'period_end' => $subscription->current_period_end,
                'terminals_count' => $terminals,
                'included_terminals' => $plan->included_terminals,
                'base_fee' => $plan->baseFee()->toString(),
                'fee_per_terminal' => $plan->feePerTerminal()->toString(),
                'lines' => $quote['lines'],
                'amount' => $quote['amount']->toString(),
                'status' => SubscriptionInvoiceStatus::ISSUED,
                'due_date' => Carbon::instance($subscription->current_period_end)->setTimezone($this->timezone())
                    ->addDays((int) config('billing.invoice_due_days', 10))->toDateString(),
                'issued_at' => $now,
            ]);
        }

        if ($invoice->status !== SubscriptionInvoiceStatus::VOID && $invoice->ledger_transaction_id === null) {
            $invoice->update(['ledger_transaction_id' => $this->ledger->chargeSubscription($invoice)->id]);
        }

        return $invoice;
    }

    protected function open(int $storeId, TariffPlan $plan, CarbonInterface $start, ?CarbonInterface $trialEndsAt, CarbonInterface $periodEnd, ?User $actor, CarbonInterface $now): StoreSubscription
    {
        $subscription = new StoreSubscription([
            'store_id' => $storeId,
            'tariff_plan_id' => $plan->id,
            'started_at' => $start,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $start,
            'current_period_end' => $periodEnd,
            'created_by' => $actor?->id,
        ]);
        $subscription->status = $this->statusFor($subscription, $now);
        $subscription->save();

        return $subscription;
    }

    protected function statusFor(StoreSubscription $subscription, CarbonInterface $now): SubscriptionStatus
    {
        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            return SubscriptionStatus::CANCELLED;
        }

        $overdue = SubscriptionInvoice::where('store_id', $subscription->store_id)
            ->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->where('due_date', '<', Carbon::instance($now)->setTimezone($this->timezone())->toDateString())
            ->exists();

        return match (true) {
            $overdue => SubscriptionStatus::PAST_DUE,
            $subscription->trial_ends_at !== null && $subscription->trial_ends_at->greaterThan($now) => SubscriptionStatus::TRIALING,
            default => SubscriptionStatus::ACTIVE,
        };
    }

    protected function syncStoreStatus(int $storeId, CarbonInterface $now): void
    {
        $subscription = StoreSubscription::query()->current()->where('store_id', $storeId)->first();
        if ($subscription && ($status = $this->statusFor($subscription, $now)) !== $subscription->status) {
            $subscription->update(['status' => $status]);
        }
    }

    /**
     * Whether the trial covers any part of a period starting at $from: such a period is not billed.
     */
    protected function trialCovers(StoreSubscription $subscription, CarbonInterface $from): bool
    {
        return $subscription->trial_ends_at !== null && $subscription->trial_ends_at->greaterThan($from);
    }

    protected function now(?CarbonInterface $now): Carbon
    {
        return Carbon::instance($now ?? now())->setTimezone((string) config('app.timezone'));
    }
}
