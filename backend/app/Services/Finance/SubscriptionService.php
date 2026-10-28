<?php

namespace App\Services\Finance;

use App\Enums\BillingPeriod;
use App\Enums\DeviceType;
use App\Enums\InvoiceKind;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Billing\CommissionStats;
use App\Services\Finance\Billing\SubscriptionStatusResolver;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
 * - Every change first bills the periods that have already ended (the daily run may not have reached them yet), so a
 *   change made after a month ends never loses that month nor starts a period that ends before it begins (P1-34).
 * - A deleted store's subscription is closed once what it owes is billed (Store::softDeleted, and the run for rows
 *   left from before); the month in progress at the deletion and later ones are never billed.
 *
 * P4-21, Billing v2 plans (version 2: Start, Asosiy, Pro):
 * - A month's invoice: the base fee, each extra kassa and KDS over the plan's (most active at once in the month, per
 *   type), 50% off the base fee when the store's commission in the month before was over the threshold
 *   (CommissionStats), and, in the first month after an immediate upgrade, a credit for the days before it at the
 *   old plan's price (the month costs old × days before / days + new × the rest).
 * - Yearly: 12 billing months are billed in advance when the term starts (the first month the trial does not touch),
 *   at the plan's yearly fee; the months of the term bill only their extra devices; the next term is billed when it
 *   ends. Cancelling, changing the period or moving to a lower plan waits for the term's end.
 * - A change to a lower plan always waits for the period's end; a higher plan may apply now (prorated).
 * - A P1-23 plan keeps its formula and rules exactly.
 */
class SubscriptionService
{
    public function __construct(
        protected LedgerService $ledger,
        protected TerminalCounter $terminals,
        protected AuditLogger $audit,
        protected SubscriptionStatusResolver $statuses,
        protected CommissionStats $commissions,
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
     * Put a store on a plan. A store without a subscription starts now (with the plan's trial if it never had one),
     * on $period (monthly by default; yearly only on a v2 plan); otherwise the change waits for the end of the period,
     * or applies now when $immediately. Choosing the current plan again drops a pending change or cancellation.
     *
     * P4-21: between v2 plans a lower plan always waits for the period's end (a yearly one: the term's end); a higher
     * one applied now is prorated.
     */
    public function assignPlan(Store $store, TariffPlan $plan, bool $immediately = false, ?User $actor = null, ?CarbonInterface $now = null, ?BillingPeriod $period = null): StoreSubscription
    {
        if (!$plan->is_active) {
            throw new \DomainException("Bu tarif o'chirilgan, uni biriktirib bo'lmaydi.");
        }
        if ($period === BillingPeriod::YEARLY && !$plan->isV2()) {
            throw new \DomainException("Yillik davr faqat yangi tariflarda (Start, Asosiy, Pro) bor.");
        }

        $now = $this->now($now);

        return DB::transaction(function () use ($store, $plan, $immediately, $actor, $now, $period) {
            // One writer per store, so two assignments cannot both find no current subscription
            Store::whereKey($store->id)->lockForUpdate()->firstOrFail();
            $current = StoreSubscription::query()->current()->where('store_id', $store->id)->lockForUpdate()->first();
            // A month that ended before the daily run is billed under the plan it was used on, before anything changes
            $current = $current ? $this->billEndedPeriods($current, $now)[1] : null;

            if (!$current) {
                $isFirst = !StoreSubscription::where('store_id', $store->id)->exists();
                $subscription = $this->open($store->id, $plan, $now, $isFirst && $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null, $this->nextMonthStart($now), $actor, $now, [
                    'billing_period' => $period ?? BillingPeriod::MONTHLY,
                ]);
                $this->audit->record('subscription.started', $subscription, [], ['plan' => $plan->code, 'billing_period' => $subscription->billing_period->value], [], ['actor' => $actor, 'store_id' => $store->id]);
                $this->startYearlyTerm($subscription, $now);

                return $subscription;
            }

            if ($current->tariff_plan_id === $plan->id) {
                $current->update(['next_tariff_plan_id' => null, 'cancel_at_period_end' => false]);

                return $current;
            }

            $currentPlan = $current->plan()->firstOrFail();
            $from = $currentPlan->code;
            $bothV2 = $currentPlan->isV2() && $plan->isV2();
            if ($bothV2 && $plan->rank() < $currentPlan->rank()) {
                // A lower plan: at the period's end, whatever was asked
                $immediately = false;
            }
            if ($current->isYearly() && !$plan->isV2()) {
                throw new \DomainException("Yillik obunani faqat yangi tariflardan biriga o'tkazish mumkin.");
            }

            if (!$immediately) {
                $current->update(['next_tariff_plan_id' => $plan->id, 'cancel_at_period_end' => false]);
                $this->audit->record('subscription.plan_change_scheduled', $current, ['plan' => $from], ['plan' => $plan->code], ['from' => $this->changesAt($current)->toIso8601String()], ['actor' => $actor, 'store_id' => $store->id]);

                return $current;
            }

            $trialEndsAt = $current->trial_ends_at?->greaterThan($now) ? $current->trial_ends_at : null;
            $periodEnd = $current->current_period_end;
            $current->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $now, 'next_tariff_plan_id' => null, 'cancel_at_period_end' => false]);

            $subscription = $this->open($store->id, $plan, $now, $trialEndsAt, $periodEnd, $actor, $now, [
                'billing_period' => $current->billing_period,
                'next_billing_period' => $current->next_billing_period,
                'term_ends_at' => $current->term_ends_at,
                // The month's credit for the days before the upgrade (a monthly v2 subscription out of its trial)
                'prorate_from_plan_id' => $bothV2 && !$current->isYearly() && $trialEndsAt === null ? $currentPlan->id : null,
                'price_lock_plan_id' => $current->price_lock_plan_id,
                'price_locked_until' => $current->price_locked_until,
            ]);
            $this->audit->record('subscription.plan_changed', $subscription, ['plan' => $from], ['plan' => $plan->code], [], ['actor' => $actor, 'store_id' => $store->id]);

            if ($bothV2 && $subscription->isYearly() && $subscription->term_ends_at !== null) {
                $this->issueUpgrade($subscription, $currentPlan, $plan, $now);
            } else {
                $this->startYearlyTerm($subscription, $now);
            }

            return $subscription;
        });
    }

    /**
     * P4-21: switch between monthly and yearly (v2 plans only). Before the first yearly term starts (during the
     * trial) it applies at once; otherwise monthly → yearly from the next month (its term is billed then) and
     * yearly → monthly when the paid term ends. Choosing the current period again drops a pending switch.
     */
    public function changePeriod(StoreSubscription $subscription, BillingPeriod $period, ?User $actor = null, ?CarbonInterface $now = null): StoreSubscription
    {
        $now = $this->now($now);

        return DB::transaction(function () use ($subscription, $period, $actor, $now) {
            Store::whereKey($subscription->store_id)->lockForUpdate()->firstOrFail();
            $subscription = StoreSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if (!$subscription->isCurrent()) {
                throw new \DomainException('Obuna tugagan.');
            }
            $subscription = $this->billEndedPeriods($subscription, $now)[1] ?? throw new \DomainException('Obuna tugagan.');

            if (!$subscription->plan()->firstOrFail()->isV2()) {
                throw new \DomainException("Yillik davr faqat yangi tariflarda (Start, Asosiy, Pro) bor.");
            }

            $before = ['billing_period' => $subscription->billing_period->value, 'next_billing_period' => $subscription->next_billing_period?->value];

            if ($subscription->billing_period === $period) {
                $subscription->update(['next_billing_period' => null]);
            } elseif ($subscription->term_ends_at === null && ($period === BillingPeriod::MONTHLY || $this->trialCovers($subscription, $subscription->current_period_start))) {
                // No paid term yet: the switch applies now (a yearly term starts with the first billed month)
                $subscription->update(['billing_period' => $period, 'next_billing_period' => null]);
                $this->startYearlyTerm($subscription, $now);
            } else {
                $subscription->update(['next_billing_period' => $period]);
            }

            $after = ['billing_period' => $subscription->billing_period->value, 'next_billing_period' => $subscription->next_billing_period?->value];
            if ($before !== $after) {
                $this->audit->record('subscription.period_changed', $subscription, $before, $after, ['from' => $subscription->next_billing_period ? $this->changesAt($subscription)->toIso8601String() : null], ['actor' => $actor, 'store_id' => $subscription->store_id]);
            }

            return $subscription->refresh();
        });
    }

    /**
     * P4-21: give a subscription in its trial more days (no further than config('billing.max_trial_extension_days')
     * from now). The months the trial touches are not billed.
     */
    public function extendTrial(StoreSubscription $subscription, CarbonInterface $until, ?User $actor = null, ?CarbonInterface $now = null): StoreSubscription
    {
        $now = $this->now($now);
        $until = Carbon::instance($until)->setTimezone((string) config('app.timezone'));

        return DB::transaction(function () use ($subscription, $until, $actor, $now) {
            Store::whereKey($subscription->store_id)->lockForUpdate()->firstOrFail();
            $subscription = StoreSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if (!$subscription->isCurrent() || $subscription->trial_ends_at === null || !$subscription->trial_ends_at->greaterThan($now)) {
                throw new \DomainException("Sinov davri faqat sinovdagi obunada uzaytiriladi.");
            }
            if (!$until->greaterThan($subscription->trial_ends_at)) {
                throw new \DomainException("Yangi sana hozirgi sinov tugashidan keyin bo'lishi kerak.");
            }
            if ($until->greaterThan($now->copy()->addDays((int) config('billing.max_trial_extension_days', 90)))) {
                throw new \DomainException('Sinov davri ko\'pi bilan ' . (int) config('billing.max_trial_extension_days', 90) . ' kunga uzaytiriladi.');
            }

            $before = $subscription->trial_ends_at->toIso8601String();
            $subscription->update(['trial_ends_at' => $until]);
            $subscription->update(['status' => $this->statuses->statusFor($subscription, $now)]);
            $this->audit->record('subscription.trial_extended', $subscription, ['trial_ends_at' => $before], ['trial_ends_at' => $until->toIso8601String()], [], ['actor' => $actor, 'store_id' => $subscription->store_id]);

            return $subscription;
        });
    }

    /**
     * End a subscription: now (the month is not billed) or when its month ends (billed, then ended). Months that have
     * already ended are billed first. P4-21: a yearly one at period end ends with its paid term.
     */
    public function cancel(StoreSubscription $subscription, bool $immediately, ?User $actor = null, ?CarbonInterface $now = null): StoreSubscription
    {
        $now = $this->now($now);

        return DB::transaction(function () use ($subscription, $immediately, $actor, $now) {
            // The store first, as assignPlan locks them (a deleted store's too: its subscription is closed this way)
            Store::withTrashed()->whereKey($subscription->store_id)->lockForUpdate()->first();
            $subscription = StoreSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if (!$subscription->isCurrent()) {
                return $subscription;
            }

            // Ended months are billed first; they may move the subscription on (next month, next plan's row) or end it
            $current = $this->billEndedPeriods($subscription, $now)[1];
            if ($current === null) {
                return $subscription->refresh();
            }
            $subscription = $current;

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

                    return $subscription ? $this->billEndedPeriods($subscription, $now)[0] : 0;
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
     * A store was deleted (soft delete): its ended periods are billed, then its subscription ends now (the month in
     * progress is not billed, as with an immediate cancel). Store::softDeleted calls it.
     */
    public function closeForDeletedStore(Store $store, ?User $actor = null, ?CarbonInterface $now = null): void
    {
        $now = $this->now($now);

        foreach (StoreSubscription::query()->current()->where('store_id', $store->id)->orderBy('id')->get() as $subscription) {
            $this->cancel($subscription, true, $actor, $now);
        }
    }

    /**
     * Mark paid the issued invoices of stores that owe nothing, and set each current subscription's status
     * (past_due when an issued invoice is past its due date, P4-21: locked a week after that, trialing during the
     * trial, otherwise active).
     */
    public function syncStatuses(?CarbonInterface $now = null): void
    {
        $now = $this->now($now);

        SubscriptionInvoice::where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->whereIn('store_id', Store::withTrashed()->where('debt_balance', '<=', 0)->select('id'))
            ->update(['status' => SubscriptionInvoiceStatus::PAID->value, 'paid_at' => $now, 'paid_amount' => DB::raw('amount'), 'updated_at' => $now]);
        Store::withTrashed()->where('debt_balance', '<=', 0)->where('subscription_debt', '>', 0)->update(['subscription_debt' => 0]);

        foreach (StoreSubscription::query()->current()->pluck('store_id')->unique() as $storeId) {
            $this->statuses->refresh((int) $storeId, $now);
        }
    }

    /**
     * fee = base + max(0, terminals - included) × per_terminal, with the lines an invoice shows (a P1-23 plan).
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
     * P4-21: a v2 plan's month [$from, $to) for the subscription: base fee (none in a month a yearly term paid for),
     * extra kassa and KDS, the discount and the upgrade credit. $terminals: devices per type counted in the month.
     *
     * @param  array{pos: int, kds: int}  $terminals
     * @return array{amount: Money, extra_terminals: int, lines: list<array{type: string, label: string, quantity: int, unit_price: string, amount: string}>}
     */
    public function quoteMonth(StoreSubscription $subscription, TariffPlan $plan, CarbonInterface $from, array $terminals): array
    {
        $lines = [];
        $amount = Money::zero();
        $add = function (string $type, string $label, int $quantity, Money $unit, Money $total) use (&$lines, &$amount) {
            $lines[] = ['type' => $type, 'label' => $label, 'quantity' => $quantity, 'unit_price' => $unit->toString(), 'amount' => $total->toString()];
            $amount = $amount->plus($total);
        };

        $prepaid = $subscription->isYearly() && $subscription->term_ends_at !== null && $subscription->term_ends_at->greaterThan($from);
        $base = $plan->baseFee();

        if (!$prepaid && $base->isPositive()) {
            $add('base', "Oylik to'lov: {$plan->name}", 1, $base, $base);

            // The month after one with more than the threshold in commission: the base fee at 50% off
            $previous = Carbon::instance($from)->setTimezone($this->timezone())->startOfMonth()->subMonthNoOverflow()->format('Y-m');
            if ($this->commissions->earnsDiscount($subscription->store_id, $previous)) {
                $percent = (string) config('billing.commission_discount.percent', '50');
                $discount = $base->percent($percent);
                $add('discount', "Chegirma {$this->percentLabel($percent)}%: {$previous} oyida komissiya " . number_format((float) config('billing.commission_discount.threshold', 1000000), 0, '.', ' ') . " so'mdan ko'p", 1, $discount->negated(), $discount->negated());
            }

            // The first month after an immediate upgrade: the days before it at the old plan's price
            if ($subscription->prorate_from_plan_id !== null && $subscription->started_at->equalTo($from) && ($old = TariffPlan::find($subscription->prorate_from_plan_id)) !== null) {
                [$daysBefore, $days] = $this->daysBefore($from);
                $difference = $base->minus($old->baseFee());
                if ($daysBefore > 0 && $difference->isPositive()) {
                    $credit = Money::of(BigDecimal::of($difference->toString())->multipliedBy($daysBefore)->dividedBy($days, Money::SCALE, RoundingMode::HalfUp));
                    $add('proration', "Tarif oshirilgunga qadar {$daysBefore} kun {$old->name} narxida", $daysBefore, $credit->negated(), $credit->negated());
                }
            }
        }

        $extra = 0;
        foreach ([DeviceType::POS, DeviceType::KDS] as $type) {
            $count = $terminals[$type->value];
            $included = $plan->includedDevices($type);
            $over = max(0, $count - $included);
            $fee = $plan->extraDeviceFee($type);
            if ($over > 0 && $fee->isPositive()) {
                $name = $type === DeviceType::KDS ? 'oshxona ekrani' : 'kassa';
                $add("extra_{$type->value}", "Qo'shimcha {$name}: {$count} ta, {$included} tasi tarifga kiradi", $over, $fee, $fee->times($over));
                $extra += $over;
            }
        }

        // billing:migrate-plans: the first month on the new plan never costs more than the P1-23 plan did
        if ($subscription->price_lock_plan_id !== null && $subscription->price_locked_until !== null && $subscription->price_locked_until->greaterThan($from)
            && ($locked = TariffPlan::find($subscription->price_lock_plan_id)) !== null) {
            $legacy = $this->quote($locked, $terminals['pos'] + $terminals['kds'])['amount'];
            if ($amount->isGreaterThan($legacy)) {
                $credit = $amount->minus($legacy);
                $add('price_lock', "Narx o'zgarmaydi: avvalgi \"{$locked->name}\" tarifi narxi", 1, $credit->negated(), $credit->negated());
            }
        }

        return ['amount' => Money::max(Money::zero(), $amount), 'extra_terminals' => $extra, 'lines' => $lines];
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
        $until = Carbon::instance($now)->min($subscription->current_period_end);
        $counted = $this->terminals->maxConcurrent($store->id, $subscription->current_period_start, $until);
        // An active terminal keeps counting until the period ends
        $counted = max($counted, $activeNow);
        $inTrial = $this->trialCovers($subscription, $subscription->current_period_start);
        $quote = $plan->isV2()
            ? $this->quoteMonth($subscription, $plan, $subscription->current_period_start, $this->countByType($store->id, $subscription->current_period_start, $until, true))
            : $this->quote($plan, $counted);
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
                // P4-21
                'billing_period' => $subscription->billing_period->value,
                'next_billing_period' => $subscription->next_billing_period?->value,
                'term_ends_at' => $subscription->term_ends_at?->toIso8601String(),
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

            $before = $invoice->status->value;
            // P4-21: void first, so the reversal takes what was unpaid of it out of the subscription part of the debt
            $invoice->update([
                'status' => SubscriptionInvoiceStatus::VOID,
                'voided_at' => $now,
                'voided_by' => $actor?->id,
                'void_reason' => $reason,
            ]);
            $reversal = $this->ledger->reverseSubscriptionCharge($invoice, $actor?->id, $reason);
            $invoice->update(['void_transaction_id' => $reversal?->id]);

            $this->audit->record('subscription_invoice.voided', $invoice, ['status' => $before], ['status' => 'void'], ['number' => $invoice->number, 'reason' => $reason], ['actor' => $actor]);

            return $invoice;
        });

        $this->statuses->refresh($invoice->store_id, $now);

        return $invoice;
    }

    /**
     * Mark an issued invoice paid by hand (money received outside the top-up flow). The ledger is not touched: the
     * payment itself is booked as a top-up, which reduces the debt (and, P4-21, pays the oldest invoices first). The
     * mark ends the dunning for it; what the ledger has not paid of it stays in the subscription part of the debt.
     */
    public function markPaid(SubscriptionInvoice $invoice, ?User $actor = null, ?CarbonInterface $now = null): SubscriptionInvoice
    {
        $now = $this->now($now);

        $updated = SubscriptionInvoice::whereKey($invoice->id)->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->update(['status' => SubscriptionInvoiceStatus::PAID->value, 'paid_at' => $now, 'updated_at' => $now]);

        $invoice->refresh();
        if ($updated) {
            $this->audit->record('subscription_invoice.paid', $invoice, ['status' => 'issued'], ['status' => 'paid'], ['number' => $invoice->number], ['actor' => $actor]);
            $this->statuses->refresh($invoice->store_id, $now);
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
     * When a scheduled change of the subscription applies: the end of its month, or of its paid yearly term.
     */
    public function changesAt(StoreSubscription $subscription): Carbon
    {
        return $subscription->isYearly() && $subscription->term_ends_at !== null
            ? Carbon::instance($subscription->term_ends_at)
            : Carbon::instance($subscription->current_period_end);
    }

    /**
     * Bill every period of the subscription that has ended by $now, oldest first, as the scheduled run does (the
     * caller holds the subscription's row lock). A deleted store's periods are billed while they ended before its
     * deletion; then the subscription ends (the month in progress at the deletion is not billed, as with an immediate
     * cancel). Returns the invoices created and the subscription current afterwards (the same row moved
     * on, the next plan's row, or null when it ended).
     *
     * @return array{0: int, 1: StoreSubscription|null}
     */
    protected function billEndedPeriods(StoreSubscription $subscription, CarbonInterface $now): array
    {
        $deletedAt = Store::withTrashed()->whereKey($subscription->store_id)->value('deleted_at');
        $deletedAt = $deletedAt !== null ? Carbon::parse($deletedAt) : null;
        $created = 0;
        $current = $subscription;

        while ($current && $current->isCurrent() && $current->current_period_end->lessThanOrEqualTo($now)
            && ($deletedAt === null || $current->current_period_end->lessThanOrEqualTo($deletedAt))) {
            [$issued, $current] = $this->closePeriod($current, $now);
            $created += $issued;
        }

        if ($current && $current->isCurrent() && $deletedAt !== null) {
            $current->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $now, 'next_tariff_plan_id' => null, 'cancel_at_period_end' => false]);
            $this->audit->record('subscription.cancelled', $current, [], [], ['immediately' => true, 'store_deleted' => true], ['store_id' => $current->store_id]);
            $current = null;
        }

        return [$created, $current];
    }

    /**
     * Bill the subscription's current period (it has ended) and move it on: to the next month, to the plan chosen
     * for next month (a new row), or to its end. Returns how many invoices it created (the month's; P4-21: and a new
     * yearly term's) and the subscription that is current afterwards.
     *
     * @return array{0: int, 1: StoreSubscription|null}
     */
    protected function closePeriod(StoreSubscription $subscription, CarbonInterface $now): array
    {
        $from = $subscription->current_period_start;
        $to = $subscription->current_period_end;
        $created = 0;
        $this->assertPeriod($from, $to, $subscription->id);

        if (!$this->trialCovers($subscription, $from)) {
            $plan = $subscription->plan()->firstOrFail();
            if ($plan->isV2()) {
                $counts = $this->countByType($subscription->store_id, $from, $to);
                $quote = $this->quoteMonth($subscription, $plan, $from, $counts);
                $terminals = $counts['pos'] + $counts['kds'];
            } else {
                $terminals = $this->terminals->maxConcurrent($subscription->store_id, $from, $to);
                $quote = $this->quote($plan, $terminals);
            }

            if ($quote['amount']->isPositive()) {
                $invoice = $this->issue($subscription, $plan, $terminals, $quote, $now);
                $created += $invoice->wasRecentlyCreated ? 1 : 0;
            }
        }

        // A yearly subscription's changes wait for its paid term to end (a term not started yet: the month's end)
        $termEnds = !$subscription->isYearly() || $subscription->term_ends_at === null || $to->greaterThanOrEqualTo($subscription->term_ends_at);

        if ($subscription->cancel_at_period_end && $termEnds) {
            $subscription->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $to]);

            return [$created, null];
        }

        $carried = [
            'price_lock_plan_id' => $subscription->price_locked_until?->greaterThan($to) ? $subscription->price_lock_plan_id : null,
            'price_locked_until' => $subscription->price_locked_until?->greaterThan($to) ? $subscription->price_locked_until : null,
        ];

        if ($termEnds && $subscription->next_tariff_plan_id !== null && ($next = TariffPlan::find($subscription->next_tariff_plan_id))) {
            $subscription->update(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => $to]);
            $trialEndsAt = $subscription->trial_ends_at?->greaterThan($to) ? $subscription->trial_ends_at : null;
            $period = $next->isV2() ? ($subscription->next_billing_period ?? $subscription->billing_period) : BillingPeriod::MONTHLY;

            $opened = $this->open($subscription->store_id, $next, $to, $trialEndsAt, $this->nextMonthStart($to), null, $now, $carried + [
                'billing_period' => $period,
            ]);
            $created += $this->startYearlyTerm($opened, $now) ? 1 : 0;

            return [$created, $opened];
        }

        $subscription->current_period_start = $to;
        $subscription->current_period_end = $this->nextMonthStart($to);
        $subscription->next_tariff_plan_id = $termEnds ? null : $subscription->next_tariff_plan_id;
        $subscription->prorate_from_plan_id = null;
        $subscription->price_lock_plan_id = $carried['price_lock_plan_id'];
        $subscription->price_locked_until = $carried['price_locked_until'];
        if ($subscription->isYearly() && $subscription->term_ends_at !== null && $to->greaterThanOrEqualTo($subscription->term_ends_at)) {
            $subscription->term_ends_at = null;
        }
        if ($termEnds && $subscription->next_billing_period !== null) {
            $subscription->billing_period = $subscription->next_billing_period;
            $subscription->next_billing_period = null;
        }
        $subscription->status = $this->statuses->statusFor($subscription, $now);
        $subscription->save();
        $created += $this->startYearlyTerm($subscription, $now) ? 1 : 0;

        return [$created, $subscription];
    }

    /**
     * P4-21: a yearly v2 subscription with no paid term whose current month the trial does not touch starts its term
     * now: 12 billing months from that month, billed in advance. Returns whether an invoice was created.
     */
    protected function startYearlyTerm(StoreSubscription $subscription, CarbonInterface $now): bool
    {
        if (!$subscription->isYearly() || $subscription->term_ends_at !== null || $this->trialCovers($subscription, $subscription->current_period_start)) {
            return false;
        }

        $plan = $subscription->plan()->firstOrFail();
        if (!$plan->isV2()) {
            return false;
        }

        $start = $subscription->current_period_start;
        $termEnds = Carbon::instance($start)->setTimezone($this->timezone())->startOfMonth()->addMonthsNoOverflow(12)
            ->setTimezone((string) config('app.timezone'));
        $subscription->update(['term_ends_at' => $termEnds]);

        $fee = $plan->yearlyFee();
        if (!$fee->isPositive()) {
            return false;
        }

        $months = (int) config('billing.yearly_months_charged', 10);
        $invoice = $this->createInvoice($subscription, $plan, InvoiceKind::YEARLY, $start, $termEnds, [
            ['type' => 'yearly', 'label' => "Yillik to'lov: {$plan->name}, 12 oy ({$months} oy narxida)", 'quantity' => 1, 'unit_price' => $fee->toString(), 'amount' => $fee->toString()],
        ], $fee, 0, $now, $this->dueFrom($now));

        return $invoice->wasRecentlyCreated;
    }

    /**
     * P4-21: an immediate upgrade of a yearly subscription: the yearly price difference for the months left in the
     * term, the current one included (difference × months / 12), billed now.
     */
    protected function issueUpgrade(StoreSubscription $subscription, TariffPlan $from, TariffPlan $to, CarbonInterface $now): ?SubscriptionInvoice
    {
        $months = $this->monthsLeft($now, $subscription->term_ends_at ?? $now);
        $difference = $to->yearlyFee()->minus($from->yearlyFee());
        if ($months <= 0 || !$difference->isPositive()) {
            return null;
        }

        $amount = Money::of(BigDecimal::of($difference->toString())->multipliedBy($months)->dividedBy(12, Money::SCALE, RoundingMode::HalfUp));
        $unit = Money::of(BigDecimal::of($difference->toString())->dividedBy(12, Money::SCALE, RoundingMode::HalfUp));

        return $this->createInvoice($subscription, $to, InvoiceKind::UPGRADE, $now, $subscription->term_ends_at ?? $now, [
            ['type' => 'upgrade', 'label' => "Tarifni oshirish: {$from->name} → {$to->name}, yillik muddatning qolgan {$months} oyi", 'quantity' => $months, 'unit_price' => $unit->toString(), 'amount' => $amount->toString()],
        ], $amount, 0, $now, $this->dueFrom($now));
    }

    /**
     * The period's invoice, created and charged at most once (unique store + month); an existing one is returned,
     * its charge made good if it is missing.
     *
     * @param  array{amount: Money, extra_terminals: int, lines: list<array<string, mixed>>}  $quote
     */
    protected function issue(StoreSubscription $subscription, TariffPlan $plan, int $terminals, array $quote, CarbonInterface $now): SubscriptionInvoice
    {
        $due = Carbon::instance($subscription->current_period_end)->setTimezone($this->timezone())
            ->addDays((int) config('billing.invoice_due_days', 10))->toDateString();

        return $this->createInvoice($subscription, $plan, InvoiceKind::MONTHLY, $subscription->current_period_start, $subscription->current_period_end, $quote['lines'], $quote['amount'], $terminals, $now, $due);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function createInvoice(StoreSubscription $subscription, TariffPlan $plan, InvoiceKind $kind, CarbonInterface $start, CarbonInterface $end, array $lines, Money $amount, int $terminals, CarbonInterface $now, string $dueDate): SubscriptionInvoice
    {
        $period = $this->periodKey($start);
        $invoice = null;
        $sequence = 1;

        if ($kind === InvoiceKind::UPGRADE) {
            $sequence = (int) SubscriptionInvoice::where('store_id', $subscription->store_id)->where('period', $period)->where('kind', $kind->value)->max('sequence') + 1;
        } else {
            $invoice = SubscriptionInvoice::where('store_id', $subscription->store_id)->where('period', $period)->where('kind', $kind->value)->lockForUpdate()->first();
        }

        if (!$invoice) {
            $number = match ($kind) {
                InvoiceKind::MONTHLY => sprintf('%s-%s-%06d', config('billing.invoice_prefix', 'OB'), str_replace('-', '', $period), $subscription->store_id),
                InvoiceKind::YEARLY => sprintf('%s-%s-%06d', config('billing.yearly_invoice_prefix', 'OBY'), str_replace('-', '', $period), $subscription->store_id),
                InvoiceKind::UPGRADE => sprintf('%s-%s-%06d-%d', config('billing.upgrade_invoice_prefix', 'OBU'), str_replace('-', '', $period), $subscription->store_id, $sequence),
            };

            $invoice = SubscriptionInvoice::create([
                'number' => $number,
                'store_id' => $subscription->store_id,
                'store_subscription_id' => $subscription->id,
                'tariff_plan_id' => $plan->id,
                'period' => $period,
                'kind' => $kind,
                'sequence' => $sequence,
                'period_start' => $start,
                'period_end' => $end,
                'terminals_count' => $terminals,
                'included_terminals' => $plan->isV2() ? $plan->included_pos + $plan->included_kds : $plan->included_terminals,
                'base_fee' => ($kind === InvoiceKind::MONTHLY ? $this->lineSum($lines, ['base']) : $amount)->toString(),
                'fee_per_terminal' => ($plan->isV2() ? Money::of($plan->extra_pos_fee) : $plan->feePerTerminal())->toString(),
                'lines' => $lines,
                'amount' => $amount->toString(),
                'paid_amount' => '0.00',
                'status' => SubscriptionInvoiceStatus::ISSUED,
                'due_date' => $dueDate,
                'issued_at' => $now,
            ]);
        }

        if ($invoice->status !== SubscriptionInvoiceStatus::VOID && $invoice->ledger_transaction_id === null) {
            $invoice->update(['ledger_transaction_id' => $this->ledger->chargeSubscription($invoice)->id]);
        }

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $attributes  P4-21 columns (billing period, term, proration, price lock)
     */
    protected function open(int $storeId, TariffPlan $plan, CarbonInterface $start, ?CarbonInterface $trialEndsAt, CarbonInterface $periodEnd, ?User $actor, CarbonInterface $now, array $attributes = []): StoreSubscription
    {
        $this->assertPeriod($start, $periodEnd);

        $subscription = new StoreSubscription([
            'store_id' => $storeId,
            'tariff_plan_id' => $plan->id,
            'started_at' => $start,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $start,
            'current_period_end' => $periodEnd,
            'created_by' => $actor?->id,
            'billing_period' => BillingPeriod::MONTHLY,
            ...$attributes,
        ]);
        $subscription->status = $this->statuses->statusFor($subscription, $now);
        $subscription->save();

        return $subscription;
    }

    /**
     * Kassa and KDS counted apart over [from, to); $withActive: a terminal active now counts (an estimate).
     *
     * @return array{pos: int, kds: int}
     */
    protected function countByType(int $storeId, CarbonInterface $from, CarbonInterface $to, bool $withActive = false): array
    {
        $counts = [];
        foreach ([DeviceType::POS, DeviceType::KDS] as $type) {
            $count = $this->terminals->maxConcurrent($storeId, $from, $to, [$type]);
            $counts[$type->value] = $withActive ? max($count, $this->terminals->activeNow($storeId, [$type])) : $count;
        }

        return ['pos' => $counts['pos'], 'kds' => $counts['kds']];
    }

    /**
     * Whole days of the month before $at's day, and the month's days (Asia/Tashkent).
     *
     * @return array{0: int, 1: int}
     */
    protected function daysBefore(CarbonInterface $at): array
    {
        $local = Carbon::instance($at)->setTimezone($this->timezone());

        return [$local->day - 1, $local->daysInMonth];
    }

    /**
     * Billing months from $now's month up to $end (exclusive), $now's month included.
     */
    protected function monthsLeft(CarbonInterface $now, CarbonInterface $end): int
    {
        $from = Carbon::instance($now)->setTimezone($this->timezone())->startOfMonth();
        $to = Carbon::instance($end)->setTimezone($this->timezone());

        return max(0, ($to->year - $from->year) * 12 + ($to->month - $from->month) + ($to->day > 1 || $to->format('H:i:s') !== '00:00:00' ? 1 : 0));
    }

    protected function dueFrom(CarbonInterface $now): string
    {
        return Carbon::instance($now)->setTimezone($this->timezone())->addDays((int) config('billing.invoice_due_days', 10))->toDateString();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<string>  $types
     */
    protected function lineSum(array $lines, array $types): Money
    {
        $sum = Money::zero();
        foreach ($lines as $line) {
            if (in_array($line['type'], $types, true)) {
                $sum = $sum->plus((string) $line['amount']);
            }
        }

        return $sum;
    }

    protected function percentLabel(string $percent): string
    {
        return rtrim(rtrim(Money::of($percent)->toString(), '0'), '.');
    }

    /**
     * The invariant every period keeps: it ends after it starts. A period that would not is a bug upstream (it used to
     * happen when a plan changed after a month ended but before the run billed it), never something to bill.
     */
    protected function assertPeriod(CarbonInterface $start, CarbonInterface $end, ?int $subscriptionId = null): void
    {
        if (!$end->greaterThan($start)) {
            throw new \LogicException(sprintf(
                'Subscription%s period would end (%s) before it starts (%s).',
                $subscriptionId !== null ? " {$subscriptionId}" : '',
                $end->toIso8601String(),
                $start->toIso8601String(),
            ));
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
