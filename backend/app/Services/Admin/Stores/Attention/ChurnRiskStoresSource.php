<?php

namespace App\Services\Admin\Stores\Attention;

use App\Enums\AdminPermission;
use App\Enums\StoreHealthLevel;
use App\Enums\StoreStatus;
use App\Models\StoreHealthScore;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\Attention\TodayAttentionSource;

/**
 * P4-22: live stores (not being onboarded, not deleted) whose last health is `churn_risk`, the lowest score first,
 * with the main reason (store_health_scores, refreshed hourly).
 */
class ChurnRiskStoresSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::STORES_VIEW;
    }

    public function kind(): string
    {
        return 'store_churn_risk';
    }

    public function label(): string
    {
        return 'Ketish xavfidagi ovqatxonalar';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $query = StoreHealthScore::query()
            ->where('level', StoreHealthLevel::CHURN_RISK->value)
            ->whereHas('store', fn ($store) => $store->whereIn('status', [
                StoreStatus::ACTIVE->value, StoreStatus::PAYMENT_REQUIRED->value, StoreStatus::FINANCE_HOLD->value,
            ]));

        $items = (clone $query)->with('store:id,name')->orderBy('score')->orderBy('store_id')->limit($limit)->get()
            ->map(function (StoreHealthScore $score) {
                $reasons = $score->reasons;

                return new AttentionItem(
                    kind: $this->kind(),
                    severity: 'warning',
                    title: (string) $score->store?->name,
                    subtitle: $this->join([isset($reasons[0]['label']) ? (string) $reasons[0]['label'] : null, count($reasons) > 1 ? '+' . (count($reasons) - 1) . ' sabab' : null]),
                    href: '/stores/' . $score->store_id,
                    since: $this->iso($score->computed_at),
                    storeId: (int) $score->store_id,
                );
            })
            ->values()->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
