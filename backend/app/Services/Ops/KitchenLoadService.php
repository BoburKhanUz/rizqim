<?php

namespace App\Services\Ops;

use App\Models\Order;
use App\Domain\Ops\KitchenState;
use App\Domain\Ops\CourierPressure;
use Illuminate\Support\Facades\Cache;

class KitchenLoadService
{
    /**
     * Get active order counts in preparation for the current store.
     */
    public static function getActivePrepCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:active_prep_count:store_{$storeId}", 15, function () use ($storeId) {
            return Order::where('store_id', $storeId)
                ->whereIn('status', ['accepted', 'preparing'])
                ->count();
        });
    }

    /**
     * Get the active courier count in transit for the current store.
     */
    public static function getActiveTransitCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:active_transit_count:store_{$storeId}", 15, function () use ($storeId) {
            return Order::where('store_id', $storeId)
                ->where('status', 'delivery')
                ->count();
        });
    }

    /**
     * Get the pending accept count for the current store.
     */
    public static function getPendingAcceptCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:pending_accept_count:store_{$storeId}", 15, function () use ($storeId) {
            return Order::where('store_id', $storeId)
                ->where('status', 'new')
                ->count();
        });
    }

    /**
     * Get KitchenState Domain Object (cached per store).
     */
    public static function getKitchenState(): KitchenState
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:kitchen_state_object:store_{$storeId}", 15, function () {
            return new KitchenState(self::getActivePrepCount());
        });
    }

    /**
     * Get CourierPressure Domain Object (cached per store).
     */
    public static function getCourierPressure(): CourierPressure
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:courier_pressure_object:store_{$storeId}", 15, function () {
            return new CourierPressure(self::getActiveTransitCount());
        });
    }

    /**
     * Get the pending accept state map (cached per store).
     */
    public static function getOperatorStateMap(): array
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:operator_state_map:store_{$storeId}", 15, function () {
            $count = self::getPendingAcceptCount();
            if ($count >= 5) {
                return [
                    'map' => 'Operators: ■■■■■',
                    'color' => 'danger',
                    'level' => 'CRITICAL',
                    'desc' => "🚨 BACKLOG ALERT: {$count} awaiting accept. Staff overloaded."
                ];
            } elseif ($count >= 2) {
                return [
                    'map' => 'Operators: ■■■□□',
                    'color' => 'warning',
                    'level' => 'HIGH',
                    'desc' => "⚠️ Accept backlog: {$count} pending accept. Expect minor delays."
                ];
            } elseif ($count >= 1) {
                return [
                    'map' => 'Operators: ■■□□□',
                    'color' => 'warning',
                    'level' => 'STEADY',
                    'desc' => '1 order awaiting accept. Action required.'
                ];
            }
            return [
                'map' => 'Operators: ■□□□□',
                'color' => 'success',
                'level' => 'OPTIMAL',
                'desc' => 'Accept queue empty. Active monitoring.'
            ];
        });
    }
}
