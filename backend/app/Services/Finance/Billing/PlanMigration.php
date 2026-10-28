<?php

namespace App\Services\Finance\Billing;

use App\Enums\DeviceType;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\SubscriptionService;
use App\Services\Finance\TerminalCounter;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P4-21: `php artisan billing:migrate-plans [--dry-run]` — existing stores onto Billing v2, safely (docs/BILLING.md,
 * "Mavjud do'konlarni o'tkazish"). Per store:
 * - a P1-23 plan with a fee → the v2 plan that has every feature the store uses (bookings, deposits, nasiya, KDS,
 *   service charge: Asosiy; otherwise Start), from the next month (the month in progress keeps the old price), and
 *   its first month on the new plan costs at most what the old plan did (price lock);
 * - the commission-only P1-23 plan → ended now (it billed nothing);
 * - commission: the old rate is kept as the store's flat rate when it was not the old default 10%; a store at 10% moves
 *   to the channel rates (own delivery stays 10%, pickup 5%, RIZQIM courier 15%);
 * - the debt is split: unpaid invoices into subscription_debt (at most the debt); a store blocked only by invoices is
 *   unblocked (the credit limit counts the commission debt only);
 * - a store with terminals but no plan is reported (an admin assigns its plan).
 * Every change is audited. --dry-run does exactly the same inside a transaction that is rolled back, so its report is
 * what the real run will do. Running it again changes nothing.
 */
class PlanMigration
{
    private const OLD_DEFAULT_RATE = '10.00';

    public function __construct(
        protected SubscriptionService $subscriptions,
        protected SubscriptionDebt $debt,
        protected CommissionPolicy $commission,
        protected TerminalCounter $terminals,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>|null  $storeIds
     * @return list<array<string, mixed>> one row per store that changes or needs attention
     */
    public function run(bool $dryRun, ?array $storeIds = null, ?CarbonInterface $now = null): array
    {
        $now = Carbon::instance($now ?? now());
        $rows = [];

        DB::beginTransaction();
        try {
            $stores = Store::query()->when($storeIds, fn ($query) => $query->whereIn('id', $storeIds))->orderBy('id')->pluck('id');
            foreach ($stores as $storeId) {
                $row = DB::transaction(fn () => $this->migrate((int) $storeId, $now));
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();
        app(Entitlements::class)->forget();

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function migrate(int $storeId, Carbon $now): ?array
    {
        $store = Store::whereKey($storeId)->lockForUpdate()->firstOrFail();
        $subscription = StoreSubscription::query()->current()->where('store_id', $storeId)->with(['plan', 'nextPlan'])->latest('id')->first();
        $plan = $subscription?->plan;
        $actions = [];
        $row = [
            'store_id' => $storeId,
            'store' => (string) $store->name,
            'plan_from' => $plan?->code,
            'plan_to' => null,
            'from' => null,
            'price_old' => null,
            'price_new' => null,
            'first_month' => null,
            'commission_from' => $this->commissionLabel($store, $plan),
            'commission_to' => null,
            'subscription_debt' => null,
            'unblocked' => false,
            'actions' => [],
        ];

        // 1. The plan
        if ($plan !== null && !$plan->isV2()) {
            if (!$plan->hasSubscriptionFee()) {
                $this->subscriptions->cancel($subscription, true, null, $now);
                $actions[] = 'commission_plan_ended';
            } elseif ($subscription->nextPlan?->isV2()) {
                $row['plan_to'] = $subscription->nextPlan->code;
            } else {
                $target = $this->targetFor($storeId);
                $row['plan_to'] = $target->code;
                $counts = ['pos' => $this->terminals->activeNow($storeId, [DeviceType::POS]), 'kds' => $this->terminals->activeNow($storeId, [DeviceType::KDS])];
                $old = $this->subscriptions->quote($plan, $counts['pos'] + $counts['kds'])['amount'];
                $new = $this->subscriptions->quoteMonth(new StoreSubscription(['store_id' => $storeId, 'billing_period' => \App\Enums\BillingPeriod::MONTHLY]), $target, $now, $counts)['amount'];
                [$row['price_old'], $row['price_new'], $row['first_month']] = [$old->toString(), $new->toString(), Money::min($old, $new)->toString()];

                $current = $this->subscriptions->assignPlan($store, $target, false, null, $now);
                $current->update([
                    'price_lock_plan_id' => $plan->id,
                    'price_locked_until' => $this->subscriptions->nextMonthStart($current->current_period_end),
                ]);
                $row['from'] = $current->current_period_end->toIso8601String();
                $actions[] = 'plan_scheduled';
            }
        } elseif ($plan === null && $this->terminals->activeNow($storeId) > 0) {
            $actions[] = 'terminals_without_plan';
        }

        // 2. Commission: a rate other than the old default is kept as the store's own flat rate
        $legacyRate = $plan !== null && !$plan->isV2() ? CommissionPolicy::normalize($plan->commission_percent) : null;
        $effective = $legacyRate ?? $this->commission->flatRate($store);
        $flat = $effective === null || $effective === self::OLD_DEFAULT_RATE ? null : $effective;
        $before = $this->commission->flatRate($store);
        if ($flat !== $before) {
            $store->forceFill(['commission_rate' => $flat])->save();
            $actions[] = $flat === null ? 'commission_by_channel' : 'commission_flat_kept';
        }
        $store->refresh();
        $row['commission_to'] = $this->commissionLabel($store, null);

        // 3. The debt split, and a store blocked only by its invoices unblocked
        $split = $this->debt->rebuild($store, $now);
        if ($split['before'] !== $split['after']) {
            $row['subscription_debt'] = "{$split['before']} → {$split['after']}";
            $actions[] = 'debt_split';
        }
        $store->refresh();
        if (($store->status->value ?? $store->status) === StoreStatus::PAYMENT_REQUIRED->value && !$store->exceedsCreditLimit()) {
            $store->forceFill(['status' => StoreStatus::ACTIVE->value])->save();
            $row['unblocked'] = true;
            $actions[] = 'unblocked';
        }

        if ($actions === []) {
            return null;
        }

        $row['actions'] = $actions;
        if (array_diff($actions, ['terminals_without_plan']) !== []) {
            $this->audit->record('billing.migrated', $store, [
                'plan' => $row['plan_from'], 'commission' => $row['commission_from'],
            ], [
                'plan' => $row['plan_to'], 'commission' => $row['commission_to'], 'subscription_debt' => $store->subscription_debt,
            ], ['actions' => $actions, 'price_old' => $row['price_old'], 'price_new' => $row['price_new'], 'first_month' => $row['first_month']], ['store_id' => $storeId, 'actor' => null]);
        }

        return $row;
    }

    /**
     * The v2 plan with every gated feature the store already uses.
     */
    protected function targetFor(int $storeId): TariffPlan
    {
        $uses = DB::table('bookings')->where('store_id', $storeId)->exists()
            || DB::table('customer_accounts')->where('store_id', $storeId)->exists()
            || $this->terminals->activeNow($storeId, [DeviceType::KDS]) > 0
            || DB::table('service_fee_settings')->where('store_id', $storeId)->where('service_charge_percent', '>', 0)->exists();

        return TariffPlan::where('code', $uses ? 'asosiy' : 'start')->firstOrFail();
    }

    protected function commissionLabel(Store $store, ?TariffPlan $plan): string
    {
        $legacy = $plan !== null && !$plan->isV2() ? CommissionPolicy::normalize($plan->commission_percent) : null;
        if ($legacy !== null) {
            return "{$legacy}% (tarif)";
        }
        $flat = $this->commission->flatRate($store);

        return $flat !== null ? "{$flat}% (hamma kanal)" : 'kanal bo\'yicha';
    }
}
