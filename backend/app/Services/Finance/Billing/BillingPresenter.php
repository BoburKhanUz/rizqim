<?php

namespace App\Services\Finance\Billing;

use App\Enums\PlanFeature;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionDunningEvent;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Services\Finance\SubscriptionService;
use App\Support\Money;

/**
 * P4-21: the admin API's shapes for the tariff catalog and a store's billing (docs/API.md, "Billing v2"). Money is a
 * two-decimal string everywhere here.
 */
class BillingPresenter
{
    public const PLAN = 'array{id: int, code: string, name: string, version: int, tier: string|null, is_active: bool, sort_order: int, description: string|null, monthly_fee: string, yearly_fee: string, included_pos: int, included_kds: int, extra_pos_fee: string, extra_kds_fee: string, trial_days: int, features: list<string>, commission_percent: string|null, monthly_fee_per_terminal: string|null, included_terminals: int, subscriptions: int}';

    public const SUBSCRIPTION = 'array{id: int, store_id: int, status: string, status_label: string, plan: array{code: string, name: string, version: int}, billing_period: string, next_billing_period: string|null, next_plan: array{code: string, name: string}|null, cancel_at_period_end: bool, started_at: string, trial_ends_at: string|null, current_period: array{start: string, end: string}, term_ends_at: string|null, changes_at: string, price_locked_until: string|null, locked_at: string|null, cancelled_at: string|null}';

    public const INVOICE = 'array{uuid: string, number: string, kind: string, period: string, period_start: string, period_end: string, plan: array{code: string, name: string}|null, lines: list<array{type: string, label: string, quantity: int, unit_price: string, amount: string}>, amount: string, paid_amount: string, outstanding: string, status: string, due_date: string, issued_at: string, paid_at: string|null, voided_at: string|null, dunning: list<array{stage: string, label: string, notified: bool, recipients: int, occurred_at: string, invoice: string|null}>}';

    public function __construct(
        protected SubscriptionService $subscriptions,
        protected CommissionPolicy $commission,
        protected Entitlements $entitlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(TariffPlan $plan, ?int $subscriptions = null): array
    {
        return [
            'id' => (int) $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'version' => $plan->version,
            'tier' => $plan->tier,
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'description' => $plan->description,
            'monthly_fee' => $plan->baseFee()->toString(),
            'yearly_fee' => $plan->isV2() ? $plan->yearlyFee()->toString() : '0.00',
            'included_pos' => $plan->included_pos,
            'included_kds' => $plan->included_kds,
            'extra_pos_fee' => Money::of($plan->extra_pos_fee)->toString(),
            'extra_kds_fee' => Money::of($plan->extra_kds_fee)->toString(),
            'trial_days' => $plan->trial_days,
            'features' => $plan->isV2()
                ? array_values(array_map(fn (PlanFeature $feature) => $feature->value, array_filter(PlanFeature::cases(), fn (PlanFeature $feature) => $plan->hasFeature($feature))))
                : array_map(fn (PlanFeature $feature) => $feature->value, PlanFeature::cases()),
            // P1-23 fields, for the old plans
            'commission_percent' => $plan->commission_percent,
            'monthly_fee_per_terminal' => $plan->monthly_fee_per_terminal,
            'included_terminals' => $plan->included_terminals,
            'subscriptions' => $subscriptions ?? (int) StoreSubscription::query()->current()->where('tariff_plan_id', $plan->id)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscription(StoreSubscription $subscription): array
    {
        $subscription->loadMissing(['plan', 'nextPlan']);

        return [
            'id' => (int) $subscription->id,
            'store_id' => (int) $subscription->store_id,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'plan' => ['code' => $subscription->plan->code, 'name' => $subscription->plan->name, 'version' => $subscription->plan->version],
            'billing_period' => $subscription->billing_period->value,
            'next_billing_period' => $subscription->next_billing_period?->value,
            'next_plan' => $subscription->nextPlan ? ['code' => $subscription->nextPlan->code, 'name' => $subscription->nextPlan->name] : null,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'started_at' => $subscription->started_at->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period' => [
                'start' => $subscription->current_period_start->toIso8601String(),
                'end' => $subscription->current_period_end->toIso8601String(),
            ],
            'term_ends_at' => $subscription->term_ends_at?->toIso8601String(),
            'changes_at' => $this->subscriptions->changesAt($subscription)->toIso8601String(),
            'price_locked_until' => $subscription->price_locked_until?->toIso8601String(),
            'locked_at' => $subscription->locked_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoice(SubscriptionInvoice $invoice): array
    {
        $invoice->loadMissing(['plan:id,code,name']);
        $events = SubscriptionDunningEvent::where('subscription_invoice_id', $invoice->id)->orderBy('id')->get();

        return [
            'uuid' => $invoice->uuid,
            'number' => $invoice->number,
            'kind' => $invoice->kind->value,
            'period' => $invoice->period,
            'period_start' => $invoice->period_start->toIso8601String(),
            'period_end' => $invoice->period_end->toIso8601String(),
            'plan' => $invoice->plan ? ['code' => $invoice->plan->code, 'name' => $invoice->plan->name] : null,
            'lines' => array_values(array_map(fn (array $line) => [
                'type' => (string) $line['type'],
                'label' => (string) $line['label'],
                'quantity' => (int) $line['quantity'],
                'unit_price' => Money::of($line['unit_price'])->toString(),
                'amount' => Money::of($line['amount'])->toString(),
            ], $invoice->lines)),
            'amount' => $invoice->total()->toString(),
            'paid_amount' => Money::of($invoice->paid_amount)->toString(),
            'outstanding' => $invoice->outstanding()->toString(),
            'status' => $invoice->status->value,
            'due_date' => $invoice->due_date->toDateString(),
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'voided_at' => $invoice->voided_at?->toIso8601String(),
            'dunning' => $events->map(fn (SubscriptionDunningEvent $event) => $event->setRelation('invoice', $invoice)->toPublicArray())->values()->all(),
        ];
    }

    /**
     * A store's commission: each channel's rate and where it comes from, the overrides, the flat rate and courier.
     *
     * @return array{rates: array<string, array{rate: string, source: string, default: string}>, overrides: array<string, string>, flat_rate: string|null, delivery_courier: string}
     */
    public function commission(Store $store): array
    {
        return [
            'rates' => $this->commission->table($store),
            'overrides' => $this->commission->overrides($store),
            'flat_rate' => $this->commission->flatRate($store),
            'delivery_courier' => $this->commission->courierOf($store)->value,
        ];
    }

    /**
     * The store's billing card: plan, entitlements, debt, commission, subscriptions and recent invoices.
     *
     * @return array<string, mixed>
     */
    public function store(Store $store): array
    {
        $this->entitlements->forget((int) $store->id);
        $current = $this->subscriptions->current($store);
        $history = StoreSubscription::where('store_id', $store->id)->with(['plan', 'nextPlan'])->orderByDesc('id')->limit(20)->get();
        $invoices = SubscriptionInvoice::where('store_id', $store->id)->orderByDesc('period')->orderByDesc('id')->limit(24)->get();

        return [
            'store' => ['id' => (int) $store->id, 'name' => (string) $store->name, 'status' => $store->status->value],
            'subscription' => $current ? $this->subscription($current) : null,
            'entitlements' => $this->entitlements->toArray($store, null, true),
            'debt' => [
                'debt_balance' => Money::of($store->debt_balance)->toString(),
                'subscription_debt' => Money::of($store->subscription_debt)->toString(),
                'commission_debt' => $store->commissionDebt()->toString(),
                'credit_limit' => Money::of($store->credit_limit)->abs()->toString(),
                'over_credit_limit' => $store->exceedsCreditLimit(),
            ],
            'commission' => $this->commission($store),
            'history' => $history->map(fn (StoreSubscription $row) => $this->subscription($row))->values()->all(),
            'invoices' => $invoices->map(fn (SubscriptionInvoice $invoice) => $this->invoice($invoice))->values()->all(),
        ];
    }
}
