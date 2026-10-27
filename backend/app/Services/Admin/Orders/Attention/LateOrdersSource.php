<?php

namespace App\Services\Admin\Orders\Attention;

use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderSla;
use App\Services\Admin\Orders\OrderSlaSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-14: orders late in preparation or on the way (kind order_late, warning; critical when late by more than the
 * stage's whole limit again).
 */
class LateOrdersSource extends OrderAttentionSource
{
    public const KIND = 'order_late';

    /** @var array{accept: int, prepare: int, deliver: int}|null */
    private ?array $limits = null;

    protected function query(array $limits): Builder
    {
        $this->limits = $limits;

        return $this->orders->withStageTimes(
            $this->orders->whereSla($this->orders->base(), OrderSla::LATE, $limits, null, [OrderSlaSettings::PREPARE, OrderSlaSettings::DELIVER])
        );
    }

    protected function item(Order $order): AttentionItem
    {
        [$acceptedAt, $enRouteAt] = AdminOrderQuery::stageTimes($order);
        $sla = app(OrderSla::class)->current($order, $acceptedAt, $enRouteAt, $this->limits);
        $late = $sla['late_by_minutes'] ?? 0;
        $stage = $sla['stage'] ?? OrderSlaSettings::PREPARE;

        return new AttentionItem(
            kind: self::KIND,
            severity: $sla !== null && $late >= $sla['limit_minutes'] ? 'critical' : 'warning',
            title: self::title($order),
            subtitle: ($stage === OrderSlaSettings::DELIVER ? 'Yetkazish' : 'Tayyorlash') . " {$late} daqiqa kechikdi",
            href: self::href($order),
            since: $sla['due_at'] ?? $order->updated_at?->toIso8601String(),
            storeId: (int) $order->store_id,
        );
    }
}
