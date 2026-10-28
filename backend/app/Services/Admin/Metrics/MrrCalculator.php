<?php

namespace App\Services\Admin\Metrics;

use App\Enums\BillingPeriod;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * P4-31 Ko'rsatkichlar: MRR (monthly recurring revenue) at given moments, rebuilt from the tables so a past month needs
 * no stored snapshot (docs/API.md "Ko'rsatkichlar"):
 *
 *  - A store counts at moment t when it has a subscription row that had started and not ended by t (store_subscriptions:
 *    `started_at` <= t < `cancelled_at`) and is out of its trial (`trial_ends_at` <= t). Past-due and locked
 *    subscriptions still count: the store is a customer, only late.
 *  - What it is worth: the plan's monthly base fee (a yearly subscription: the yearly price / 12) plus the extra kassa
 *    and KDS terminals beyond what the plan includes, counted from the devices paired and not revoked at t. List
 *    prices: the 50% commission discount, a price lock and a prorated upgrade month are one-off invoice lines, not
 *    recurring revenue. A P1-23 plan: base fee + terminals beyond the included ones × the fee per terminal.
 *  - The plan prices and the subscription's billing period are read as they are now: a price change is not tracked
 *    per month, so a past month shows today's price list for the plan the store was on.
 */
class MrrCalculator
{
    private const MONTHS_IN_YEAR = 12;

    /**
     * @param  list<CarbonImmutable>  $instants
     * @return list<MrrSnapshot> one per instant, in the same order
     */
    public function at(array $instants): array
    {
        if ($instants === []) {
            return [];
        }

        $earliest = $this->column(min($instants));
        $latest = $this->column(max($instants));

        /** @var Collection<int, Collection<int, StoreSubscription>> $subscriptions */
        $subscriptions = StoreSubscription::query()
            ->where('started_at', '<=', $latest)
            ->where(fn ($query) => $query->whereNull('cancelled_at')->orWhere('cancelled_at', '>', $earliest))
            ->where(fn ($query) => $query->where('status', '!=', 'cancelled')->orWhereNotNull('cancelled_at'))
            ->get(['id', 'store_id', 'tariff_plan_id', 'billing_period', 'started_at', 'trial_ends_at', 'cancelled_at'])
            ->groupBy('store_id');

        $plans = TariffPlan::query()->get()->keyBy('id');
        $devices = $this->devices($subscriptions->keys()->all(), $earliest, $latest);

        $snapshots = [];
        foreach ($instants as $instant) {
            $byStore = [];
            $trialing = 0;
            foreach ($subscriptions as $storeId => $rows) {
                $subscription = $this->current($rows, $instant);
                $plan = $subscription ? $plans->get($subscription->tariff_plan_id) : null;
                if ($subscription === null || $plan === null) {
                    continue;
                }
                if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->greaterThan($instant)) {
                    $trialing++;

                    continue;
                }
                $amount = $this->worth($plan, $subscription, $this->countDevices($devices[$storeId] ?? [], $instant));
                if ($amount->isPositive()) {
                    $byStore[(int) $storeId] = $amount;
                }
            }
            $snapshots[] = new MrrSnapshot($byStore, $trialing);
        }

        return $snapshots;
    }

    /**
     * @param  Collection<int, StoreSubscription>  $rows
     */
    private function current(Collection $rows, CarbonImmutable $at): ?StoreSubscription
    {
        return $rows
            ->filter(fn (StoreSubscription $row) => $row->started_at->lessThanOrEqualTo($at) && ($row->cancelled_at === null || $row->cancelled_at->greaterThan($at)))
            ->sortByDesc('id')
            ->first();
    }

    /**
     * @param  array{pos: int, kds: int}  $counts
     */
    private function worth(TariffPlan $plan, StoreSubscription $subscription, array $counts): BigDecimal
    {
        if (!$plan->isV2()) {
            $extra = max(0, $counts['pos'] + $counts['kds'] - max(0, $plan->included_terminals));

            return BigDecimal::of($plan->baseFee()->toString())
                ->plus(BigDecimal::of($plan->feePerTerminal()->toString())->multipliedBy($extra))
                ->toScale(2, RoundingMode::HALF_UP);
        }

        $base = $subscription->billing_period === BillingPeriod::YEARLY
            ? BigDecimal::of($plan->yearlyFee()->toString())->dividedBy(self::MONTHS_IN_YEAR, 2, RoundingMode::HALF_UP)
            : BigDecimal::of($plan->baseFee()->toString());

        $extras = BigDecimal::zero();
        foreach ([DeviceType::POS, DeviceType::KDS] as $type) {
            $over = max(0, $counts[$type->value] - $plan->includedDevices($type));
            $extras = $extras->plus(BigDecimal::of($plan->extraDeviceFee($type)->toString())->multipliedBy($over));
        }

        return $base->plus($extras)->toScale(2, RoundingMode::HALF_UP);
    }

    /**
     * Kassa and KDS terminals paired and not revoked at the moment.
     *
     * @param  list<array{type: string, from: int, until: int|null}>  $devices
     * @return array{pos: int, kds: int}
     */
    private function countDevices(array $devices, CarbonImmutable $at): array
    {
        $counts = ['pos' => 0, 'kds' => 0];
        $time = $at->getTimestamp();
        foreach ($devices as $device) {
            if ($device['from'] <= $time && ($device['until'] === null || $device['until'] > $time)) {
                $counts[$device['type']]++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<int|string>  $storeIds
     * @return array<int, list<array{type: string, from: int, until: int|null}>>
     */
    private function devices(array $storeIds, string $earliest, string $latest): array
    {
        if ($storeIds === []) {
            return [];
        }

        $byStore = [];
        Device::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))
            ->where('created_at', '<=', $latest)
            ->where(fn ($query) => $query->whereNull('revoked_at')->orWhere('revoked_at', '>', $earliest))
            ->get(['id', 'store_id', 'type', 'created_at', 'revoked_at'])
            ->each(function (Device $device) use (&$byStore) {
                $byStore[(int) $device->store_id][] = [
                    'type' => $device->type->value,
                    'from' => (int) $device->created_at?->getTimestamp(),
                    'until' => $device->revoked_at?->getTimestamp(),
                ];
            });

        return $byStore;
    }

    /**
     * An instant as the timestamp columns hold it (app timezone wall time).
     */
    private function column(CarbonImmutable $at): string
    {
        return $at->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
