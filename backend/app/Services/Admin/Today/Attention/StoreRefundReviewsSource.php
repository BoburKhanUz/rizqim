<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;

/**
 * Wave P: provider refunds on a store's own cashbox of a delivered order (a Payme CancelTransaction signed with the
 * store's own key, or a refund in the store's own Paylov cabinet) that keep RIZQIM's commission until a manager approves or dismisses them
 * (orders.store_refund_review_at, partial index; App\Services\Payment\StoreRefundReview). Always critical: platform
 * money waits on the decision.
 */
class StoreRefundReviewsSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_VIEW;
    }

    public function kind(): string
    {
        return 'store_refund_review';
    }

    public function label(): string
    {
        return "Ovqatxona kassasidan qaytarishlar";
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function collect(int $limit): array
    {
        $query = Order::query()->whereNotNull('store_refund_review_at');

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('store_refund_review_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'short_code', 'store_id', 'total_price', 'commission_amount', 'payment_method', 'store_refund_review_at'])
            ->map(fn (Order $order) => new AttentionItem(
                kind: $this->kind(),
                severity: $this->severity(),
                title: $this->join(['#' . ($order->short_code ?? substr((string) $order->uuid, 0, 8)), $order->store?->name]) ?? '',
                subtitle: $this->join([
                    $this->money($order->total_price),
                    ucfirst((string) $order->payment_method) . " qaytarishi, komissiya " . $this->money($order->commission_amount) . ' saqlanmoqda',
                ]),
                href: '/orders/' . $order->uuid,
                since: $this->iso($order->store_refund_review_at),
                storeId: (int) $order->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
