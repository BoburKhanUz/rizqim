<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\StoreTopupRequest;
use App\Services\Admin\Attention\AttentionItem;

/**
 * Balance top-ups (Click / Payme) still pending 30 minutes after they were started, from the last 3 days: either the
 * owner left the payment page, or the provider took the money and its callback did not arrive. Older ones are
 * abandoned checkouts and are not shown. Information only: Moliya checks the provider's cabinet.
 */
class StuckTopupsSource extends TodayAttentionSource
{
    public const AFTER_MINUTES = 30;

    public const WITHIN_HOURS = 72;

    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_VIEW;
    }

    public function kind(): string
    {
        return 'topup_pending';
    }

    public function label(): string
    {
        return "To'lanmay qolgan to'ldirishlar";
    }

    public function severity(): string
    {
        return 'info';
    }

    public function collect(int $limit): array
    {
        $query = StoreTopupRequest::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(self::AFTER_MINUTES))
            ->where('created_at', '>=', now()->subHours(self::WITHIN_HOURS));

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'store_id', 'amount', 'provider', 'created_at'])
            ->map(fn (StoreTopupRequest $topup) => new AttentionItem(
                kind: $this->kind(),
                severity: 'info',
                title: $this->join([$topup->store?->name, $this->money($topup->amount)]) ?? '',
                subtitle: ucfirst((string) $topup->provider) . " orqali balansni to'ldirish to'lanmagan",
                href: '/finance?topup=' . $topup->uuid,
                since: $this->iso($topup->created_at),
                storeId: (int) $topup->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
