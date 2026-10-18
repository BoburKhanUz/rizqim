<?php

namespace App\Services\Ops;

use App\Models\Order;
use App\Domain\Ops\RushWindow;
use Illuminate\Support\Facades\Cache;

class DelayDetectionService
{
    /**
     * Get the RushWindow domain object (cached for 15s per store context).
     */
    public static function getRushWindow(): RushWindow
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:rush_window:store_{$storeId}", 15, function () {
            return new RushWindow();
        });
    }

    /**
     * Determine if we are currently in a peak rush hour.
     */
    public static function isPeakHour(): bool
    {
        return self::getRushWindow()->isPeak();
    }

    /**
     * Check if a specific order is delayed.
     */
    public static function isOrderDelayed(Order $order): bool
    {
        $statusStr = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
        
        if (in_array($statusStr, ['delivered', 'canceled'])) {
            return false;
        }

        $rush = self::getRushWindow();
        $threshold = $statusStr === 'new' ? $rush->getAcceptDelayThreshold() : $rush->getPrepDelayThreshold();
        $elapsed = $order->created_at->diffInMinutes(now());

        return $elapsed > $threshold;
    }

    /**
     * Get all active delayed orders scoped strictly to the current store (cached for 15s per store).
     */
    public static function getDelayedOrdersCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:delayed_orders_count:store_{$storeId}", 15, function () use ($storeId) {
            $activeOrders = Order::where('store_id', $storeId)
                ->whereNotIn('status', ['delivered', 'canceled'])
                ->get();
                
            return $activeOrders->filter(fn($order) => self::isOrderDelayed($order))->count();
        });
    }
}
