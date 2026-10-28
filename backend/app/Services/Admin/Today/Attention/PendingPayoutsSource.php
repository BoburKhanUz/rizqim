<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\PayoutRequest;
use App\Services\Admin\Attention\AttentionItem;

/**
 * Stores' payout requests waiting for Moliya (payout_requests.status = pending), oldest first. The card number is
 * not shown here (it is on the request's card, masked by AdminPii).
 */
class PendingPayoutsSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_VIEW;
    }

    public function kind(): string
    {
        return 'payout_pending';
    }

    public function label(): string
    {
        return "Pul yechish so'rovlari";
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $query = PayoutRequest::query()->where('status', 'pending');

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'store_id', 'amount', 'created_at'])
            ->map(fn (PayoutRequest $payout) => new AttentionItem(
                kind: $this->kind(),
                severity: 'warning',
                title: $this->join([$payout->store?->name, $this->money($payout->amount)]) ?? '',
                subtitle: "Pul yechish so'rovi tasdiq kutmoqda",
                href: '/finance?payout=' . $payout->id,
                since: $this->iso($payout->created_at),
                storeId: (int) $payout->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
