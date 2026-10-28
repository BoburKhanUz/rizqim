<?php

namespace App\Services\Admin\Orders\Attention;

use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P4-14: delivery orders ready in the kitchen with no courier (kind order_no_courier, warning; critical after
 * CRITICAL_MINUTES of waiting).
 */
class NoCourierOrdersSource extends OrderAttentionSource
{
    public const KIND = 'order_no_courier';

    public const CRITICAL_MINUTES = 10;

    protected function query(array $limits): Builder
    {
        return $this->orders->whereReadyWithoutCourier($this->orders->base());
    }

    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sla_ready_at')->orderBy('orders.id');
    }

    protected function item(Order $order): AttentionItem
    {
        $raw = $order->getAttribute('sla_ready_at');
        $since = $raw !== null ? Carbon::parse((string) $raw) : $order->updated_at;
        $minutes = self::minutesSince($since);

        return new AttentionItem(
            kind: self::KIND,
            severity: $minutes >= self::CRITICAL_MINUTES ? 'critical' : 'warning',
            title: self::title($order),
            subtitle: "Tayyor, {$minutes} daqiqadan beri kuryer yo'q",
            href: self::href($order),
            since: $since?->toIso8601String(),
            storeId: (int) $order->store_id,
        );
    }
}
