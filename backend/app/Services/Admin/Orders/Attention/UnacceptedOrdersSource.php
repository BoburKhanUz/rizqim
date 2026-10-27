<?php

namespace App\Services\Admin\Orders\Attention;

use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Orders\OrderSla;
use App\Services\Admin\Orders\OrderSlaSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-14: new orders the store has not accepted within the accept limit (kind order_unaccepted, critical).
 */
class UnacceptedOrdersSource extends OrderAttentionSource
{
    public const KIND = 'order_unaccepted';

    protected function query(array $limits): Builder
    {
        return $this->orders->whereSla($this->orders->base(), OrderSla::LATE, $limits, null, [OrderSlaSettings::ACCEPT]);
    }

    protected function item(Order $order): AttentionItem
    {
        $minutes = self::minutesSince($order->created_at);

        return new AttentionItem(
            kind: self::KIND,
            severity: 'critical',
            title: self::title($order),
            subtitle: "{$minutes} daqiqadan beri qabul qilinmadi",
            href: self::href($order),
            since: $order->created_at?->toIso8601String(),
            storeId: (int) $order->store_id,
        );
    }
}
