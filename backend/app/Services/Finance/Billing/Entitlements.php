<?php

namespace App\Services\Finance\Billing;

use App\Enums\DeviceType;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Services\Finance\TerminalCounter;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * P4-21: what a store may use under its plan — the one place every check goes through (docs/BILLING.md,
 * "Funksiyalar va cheklovlar"): GET /owner/billing/entitlements, the POS bootstrap's `entitlements`, the
 * `plan.feature:<feature>` and `billing.unlocked` route middleware and the checks in the controllers.
 *
 * - A store on a v2 plan (Start, Asosiy, Pro; trial included) has the base features and its plan's; a P1-23 plan
 *   keeps them all; a store never on a v2 plan keeps them all too while config('billing.unsubscribed_keeps_all_features')
 *   (every store before `billing:migrate-plans`); one whose v2 subscription ended has the base ones only.
 * - Locked (an invoice unpaid more than a week after its due date, worked out live, so paying unlocks at once):
 *   settings writes and terminals over the plan's are refused (`subscription_locked`, `device_limit`).
 * - NEVER gated, whatever the plan or the debt: taking payments, closing bills, printing and fiscal receipts,
 *   shifts, refunds (nothing in those routes or services asks this class).
 */
class Entitlements
{
    /** @var array<int, array<string, mixed>> per request (a scoped binding) */
    private array $context = [];

    /** @var \Illuminate\Support\Collection<int, TariffPlan>|null */
    private ?\Illuminate\Support\Collection $catalog = null;

    public function __construct(
        protected SubscriptionStatusResolver $statuses,
        protected TerminalCounter $terminals,
    ) {}

    public function allows(Store|int $store, PlanFeature $feature): bool
    {
        if ($feature->isBase()) {
            return true;
        }

        $context = $this->context($store);

        return $context['all_features'] || ($context['plan'] instanceof TariffPlan && $context['plan']->hasFeature($feature));
    }

    /**
     * @throws BillingRefusal plan_feature_missing
     */
    public function assertFeature(Store|int $store, PlanFeature $feature): void
    {
        if (!$this->allows($store, $feature)) {
            throw BillingRefusal::featureMissing($feature, $this->requiredPlan($feature));
        }
    }

    public function settingsLocked(Store|int $store): bool
    {
        return $this->context($store)['status'] === SubscriptionStatus::LOCKED->value;
    }

    /**
     * @throws BillingRefusal subscription_locked
     */
    public function assertSettingsUnlocked(Store|int $store): void
    {
        if ($this->settingsLocked($store)) {
            throw BillingRefusal::locked($this->context($store)['locked_since']);
        }
    }

    /**
     * Pairing a terminal: a KDS needs the kds feature; while locked, only up to the plan's terminals of that type (a
     * broken till is replaced: revoke the old one, pair the new one). Over the plan's otherwise: allowed, billed as
     * an extra device.
     *
     * @throws BillingRefusal
     */
    public function assertCanPair(Store|int $store, DeviceType $type): void
    {
        if ($type === DeviceType::KDS) {
            $this->assertFeature($store, PlanFeature::KDS);
        }

        $devices = $this->devices($store);
        $row = $devices[$type->value] ?? null;
        if ($row !== null && $row['included'] !== null && $this->settingsLocked($store) && $row['active'] >= $row['included']) {
            throw new BillingRefusal(
                "To'lanmagan hisob-faktura sababli tarifdagidan ortiq qurilma ulab bo'lmaydi. Eskisini o'chirib, o'rniga yangisini ulashingiz mumkin.",
                'device_limit',
                ['device_type' => $type->value, 'included' => $row['included'], 'active' => $row['active'], 'locked_since' => $this->context($store)['locked_since']],
            );
        }
    }

    /**
     * The cheapest active v2 plan that has the feature.
     */
    public function requiredPlan(PlanFeature $feature): ?TariffPlan
    {
        return $this->catalog()->first(fn (TariffPlan $plan) => $plan->hasFeature($feature));
    }

    /**
     * The payload of GET /owner/billing/entitlements and of the POS bootstrap's `entitlements` (docs/BILLING.md).
     *
     * @return array<string, mixed>
     */
    public function toArray(Store|int $store, ?CarbonInterface $now = null): array
    {
        $context = $this->context($store, $now);
        /** @var TariffPlan|null $plan */
        $plan = $context['plan'];
        /** @var StoreSubscription|null $subscription */
        $subscription = $context['subscription'];

        $features = [];
        foreach (PlanFeature::cases() as $feature) {
            $features[$feature->value] = $this->allows($store, $feature);
        }

        $required = [];
        foreach (PlanFeature::gated() as $feature) {
            $required[$feature->value] = $this->requiredPlan($feature)?->code;
        }

        return [
            'plan' => $plan !== null ? ['code' => $plan->code, 'name' => $plan->name, 'tier' => $plan->tier, 'version' => $plan->version] : null,
            'legacy' => $context['all_features'],
            'period' => $subscription?->billing_period->value,
            'status' => $context['status'],
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'current_period' => $subscription !== null ? [
                'start' => $subscription->current_period_start->toIso8601String(),
                'end' => $subscription->current_period_end->toIso8601String(),
            ] : null,
            'term_ends_at' => $subscription?->term_ends_at?->toIso8601String(),
            'locked_since' => $context['locked_since'],
            'lock_at' => $context['lock_at'],
            'settings_locked' => $context['status'] === SubscriptionStatus::LOCKED->value,
            'features' => $features,
            'required_plans' => $required,
            'devices' => $this->devices($store),
            'notice' => $context['notice'],
        ];
    }

    /**
     * Terminals by type: included in the plan (null: no limit, a store not on a v2 plan), active now, over the plan
     * (billed as extras), the price of one extra, and whether one more may be paired now. Waiter phones are personal
     * apps: unlimited and free.
     *
     * @return array<string, array<string, mixed>>
     */
    public function devices(Store|int $store): array
    {
        $context = $this->context($store);
        /** @var TariffPlan|null $plan */
        $plan = $context['plan'];
        $v2 = $plan !== null && $plan->isV2() && !$context['all_features'];
        $locked = $context['status'] === SubscriptionStatus::LOCKED->value;
        $rows = [];

        foreach ([DeviceType::POS, DeviceType::KDS] as $type) {
            $active = $this->terminals->activeNow($context['store_id'], [$type]);
            $included = $v2 ? $plan->includedDevices($type) : null;
            $allowed = $type !== DeviceType::KDS || $this->allows($store, PlanFeature::KDS);
            $rows[$type->value] = [
                'included' => $included,
                'active' => $active,
                'extra' => $included !== null ? max(0, $active - $included) : 0,
                'extra_price' => $v2 ? $plan->extraDeviceFee($type)->toString() : null,
                'can_add' => $allowed && !($locked && $included !== null && $active >= $included),
            ];
        }

        $rows['waiter'] = ['included' => null, 'unlimited' => true, 'extra_price' => '0.00', 'can_add' => true];

        return $rows;
    }

    /**
     * Forget what this request worked out (after a change in the same request, and in tests).
     */
    public function forget(?int $storeId = null): void
    {
        $this->catalog = null;
        if ($storeId === null) {
            $this->context = [];
        } else {
            unset($this->context[$storeId]);
        }
    }

    /**
     * @return array{store_id: int, subscription: StoreSubscription|null, plan: TariffPlan|null, all_features: bool, status: string, locked_since: string|null, lock_at: string|null, notice: array<string, mixed>|null}
     */
    protected function context(Store|int $store, ?CarbonInterface $now = null): array
    {
        $storeId = $store instanceof Store ? (int) $store->id : $store;
        if ($now === null && isset($this->context[$storeId])) {
            return $this->context[$storeId];
        }

        $now = Carbon::instance($now ?? now());
        $subscription = StoreSubscription::query()->current()->where('store_id', $storeId)->with('plan')->latest('id')->first();
        $plan = $subscription?->plan;

        if ($subscription === null) {
            $hadV2 = StoreSubscription::where('store_id', $storeId)
                ->whereIn('tariff_plan_id', TariffPlan::where('version', '>=', 2)->select('id'))
                ->exists();
            $allFeatures = !$hadV2 && (bool) config('billing.unsubscribed_keeps_all_features', true);
            $status = 'none';
        } else {
            $allFeatures = $plan === null || !$plan->isV2();
            $status = $this->statuses->statusFor($subscription, $now)->value;
        }

        $overdue = $subscription !== null ? $this->statuses->oldestOverdue($storeId, $now) : null;
        $lockAt = $overdue !== null && $plan?->isV2() ? $this->statuses->lockAt($overdue) : null;

        $context = [
            'store_id' => $storeId,
            'subscription' => $subscription,
            'plan' => $plan,
            'all_features' => $allFeatures,
            'status' => $status,
            'locked_since' => $status === SubscriptionStatus::LOCKED->value && $lockAt !== null ? $lockAt->toIso8601String() : null,
            'lock_at' => $lockAt?->toIso8601String(),
            'notice' => $overdue !== null ? $this->notice($overdue, $status, $lockAt, $now) : null,
        ];

        return $this->context[$storeId] = $context;
    }

    /**
     * The partner web's (and the till's) notice about an overdue invoice.
     *
     * @return array<string, mixed>
     */
    protected function notice(SubscriptionInvoice $invoice, string $status, ?Carbon $lockAt, CarbonInterface $now): array
    {
        $days = $this->statuses->daysOverdue($invoice, $now);
        $amount = number_format($invoice->outstanding()->roundTo(0)->toFloat(), 0, '.', ' ');
        $locked = $status === SubscriptionStatus::LOCKED->value;
        $lockDate = $lockAt?->copy()->setTimezone($this->statuses->timezone())->format('d.m.Y');

        $message = match (true) {
            $locked => "{$invoice->number} hisob-faktura ({$amount} so'm) to'lanmagan: yangi sozlamalar va tarifdagidan ortiq imkoniyatlar yopildi. To'lov qilinishi bilan darhol ochiladi. Kassa ishlashda davom etadi.",
            $lockDate !== null => "{$invoice->number} hisob-faktura ({$amount} so'm) muddati {$days} kun o'tdi. {$lockDate} dan yangi sozlamalar yopiladi. Kassa ishlashda davom etadi.",
            default => "{$invoice->number} hisob-faktura ({$amount} so'm) muddati {$days} kun o'tdi.",
        };

        return [
            'level' => $locked ? 'locked' : 'reminder',
            'message' => $message,
            'days_overdue' => $days,
            'lock_at' => $lockAt?->toIso8601String(),
            'invoice' => [
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'amount' => Money::of($invoice->amount)->toString(),
                'outstanding' => $invoice->outstanding()->toString(),
                'due_date' => $invoice->due_date->toDateString(),
            ],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, TariffPlan>
     */
    protected function catalog(): \Illuminate\Support\Collection
    {
        return $this->catalog ??= TariffPlan::query()->catalog()->where('is_active', true)->get();
    }
}
