<?php

namespace App\Services\Admin\Stores;

use App\Enums\SubscriptionStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Services\Finance\Billing\BillingPresenter;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-22: the plan, subscription and debt the Ovqatxonalar section shows (list column, filter, 360° card). The ONE place
 * the section reads billing:
 *  - card(): P4-21's store billing (BillingPresenter::store, the same answer as GET /admin/billing/stores/{id}):
 *    subscription, `entitlements` (App\Services\Finance\Billing\Entitlements: plan, period, live status incl.
 *    locked, features, device limits), `debt` (debt_balance, subscription_debt, commission_debt, credit_limit,
 *    over_credit_limit), `commission` (rate per channel), `history`, `invoices`;
 *  - forStores() and filterByPlan(): the current StoreSubscription row and its plan, one query per page (the list does
 *    not run Entitlements per row; the card does).
 */
class StorePlanReader
{
    public const SOURCE = 'billing_v2';

    public function __construct(
        private readonly BillingPresenter $billing,
    ) {}

    /**
     * The current plan of each store (null: no subscription), for list rows.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{code: string, name: string, status: string, status_label: string}>
     */
    public function forStores(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        $plans = [];
        StoreSubscription::query()
            ->current()
            ->whereIn('store_id', $storeIds)
            ->with('plan:id,code,name')
            ->orderBy('id')
            ->get(['id', 'store_id', 'tariff_plan_id', 'status'])
            ->each(function (StoreSubscription $subscription) use (&$plans) {
                if ($subscription->plan === null) {
                    return;
                }
                $plans[(int) $subscription->store_id] = [
                    'code' => (string) $subscription->plan->code,
                    'name' => (string) $subscription->plan->name,
                    'status' => $subscription->status->value,
                    'status_label' => $subscription->status->label(),
                ];
            });

        return $plans;
    }

    /**
     * Narrow a store query to a plan code, or `none` (no current subscription).
     *
     * @param  Builder<Store>  $query
     */
    public function filterByPlan(Builder $query, string $plan): void
    {
        $current = fn ($subscriptions) => $subscriptions->where('status', '!=', SubscriptionStatus::CANCELLED->value);

        if ($plan === 'none') {
            $query->whereDoesntHave('subscriptions', $current);

            return;
        }

        $query->whereHas('subscriptions', fn ($subscriptions) => $current($subscriptions)->whereHas('plan', fn ($plans) => $plans->where('code', $plan)));
    }

    /**
     * The card's "Tarif va obuna" and debt (BillingPresenter::store without its `store` part).
     *
     * @return array<string, mixed>
     */
    public function card(Store $store): array
    {
        $billing = $this->billing->store($store);
        unset($billing['store']);

        return ['source' => self::SOURCE, ...$billing];
    }
}
