<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Finance\SettlementRecoveryService;

/**
 * Delivered orders whose ledger booking failed (orders.settlement_failed_at, partial index): the store's money or
 * Rizqim's commission is not booked. Critical once the automatic retries gave up (SettlementRecoveryService), a
 * warning while they still run.
 */
class SettlementFailuresSource extends TodayAttentionSource
{
    public function __construct(
        private readonly SettlementRecoveryService $settlements,
    ) {}

    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_VIEW;
    }

    public function kind(): string
    {
        return 'settlement_failed';
    }

    public function label(): string
    {
        return 'Hisob-kitob xatolari';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function collect(int $limit): array
    {
        $query = Order::query()->whereNotNull('settlement_failed_at');
        $max = $this->settlements->maxAttempts();

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('settlement_failed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'short_code', 'store_id', 'total_price', 'settlement_failed_at', 'settlement_error', 'settlement_attempts'])
            ->map(fn (Order $order) => new AttentionItem(
                kind: $this->kind(),
                severity: $order->settlement_attempts >= $max ? 'critical' : 'warning',
                title: $this->join(['#' . ($order->short_code ?? substr((string) $order->uuid, 0, 8)), $order->store?->name]) ?? '',
                subtitle: $this->join([
                    $this->money($order->total_price),
                    $order->settlement_attempts >= $max ? "Avtomatik urinishlar tugadi" : "{$order->settlement_attempts} marta urinildi",
                    $this->short($order->getAttribute('settlement_error')),
                ]),
                href: '/orders/' . $order->uuid,
                since: $this->iso($order->settlement_failed_at),
                storeId: (int) $order->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
