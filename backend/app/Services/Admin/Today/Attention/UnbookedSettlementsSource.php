<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Finance\SettlementRecoveryService;

/**
 * Review H1: delivered, platform-collected, paid orders whose money was never credited to the store and that carry
 * no retry marker (SettlementRecoveryService::unbookedLatePayments, untouched for 15 minutes): no automatic retry
 * books them. `php artisan orders:retry-settlements <id>` (or the provider's repeated completion) books one.
 */
class UnbookedSettlementsSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_VIEW;
    }

    public function kind(): string
    {
        return 'settlement_unbooked';
    }

    public function label(): string
    {
        return "To'langan, lekin do'konga yozilmagan";
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function collect(int $limit): array
    {
        $query = SettlementRecoveryService::unbookedLatePayments();

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'short_code', 'store_id', 'total_price', 'updated_at'])
            ->map(fn (Order $order) => new AttentionItem(
                kind: $this->kind(),
                severity: 'critical',
                title: $this->join(['#' . ($order->short_code ?? substr((string) $order->uuid, 0, 8)), $order->store?->name]) ?? '',
                subtitle: $this->join([$this->money($order->total_price), "To'lov bor, hisob-kitob va qayta urinish belgisi yo'q"]),
                href: '/orders/' . $order->uuid,
                since: $this->iso($order->updated_at),
                storeId: (int) $order->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
