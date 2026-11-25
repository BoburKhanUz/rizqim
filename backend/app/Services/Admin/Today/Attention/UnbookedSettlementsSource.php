<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Finance\SettlementRecoveryService;

/**
 * Review H1: delivered, platform-collected, paid orders whose money was never credited to the store and that carry
 * no retry marker (SettlementRecoveryService::unbookedLatePayments: paid between 15 minutes and 30 days ago): no
 * automatic retry books them, nor the admin panel's retry button (it books marked orders only, on purpose: finance
 * may already have credited such an order by hand, and an adjustment carries no order_id). The subtitle says so:
 * check the store's finance adjustments first, then, on the server, `php artisan orders:retry-settlements <id>
 * --confirm-unbooked` (or the provider's repeated completion books it).
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
            ->select(['id', 'uuid', 'short_code', 'store_id', 'total_price'])
            ->selectSub(SettlementRecoveryService::paidAtSubquery(), 'paid_at')
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => new AttentionItem(
                kind: $this->kind(),
                severity: 'critical',
                title: $this->join(['#' . ($order->short_code ?? substr((string) $order->uuid, 0, 8)), $order->store?->name]) ?? '',
                subtitle: $this->join([
                    $this->money($order->total_price),
                    "To'lov bor, hisob-kitob va qayta urinish belgisi yo'q",
                    "Paneldagi «qayta urinish» buni yozmaydi: avval do'konning moliya tuzatishlarini tekshiring (pul qo'lda yozilgan bo'lishi mumkin), "
                        . "keyin serverda: php artisan orders:retry-settlements {$order->id} --confirm-unbooked",
                ]),
                href: '/orders/' . $order->uuid,
                since: $this->iso($this->paidAt($order)),
                storeId: (int) $order->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }

    private function paidAt(Order $order): ?\Carbon\CarbonInterface
    {
        $paidAt = $order->getAttribute('paid_at');

        return $paidAt !== null ? \Illuminate\Support\Carbon::parse((string) $paidAt) : null;
    }
}
