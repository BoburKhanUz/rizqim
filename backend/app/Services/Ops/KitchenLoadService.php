<?php

namespace App\Services\Ops;

use App\Domain\Ops\KitchenState;
use App\Domain\Ops\CourierPressure;
use Illuminate\Support\Facades\Cache;

/**
 * Kitchen, courier and accept-queue load of the current store (a branch manager's branch, see OpsContext).
 */
class KitchenLoadService
{
    /**
     * Get active order counts in preparation for the current store.
     */
    public static function getActivePrepCount(): int
    {
        return Cache::remember(OpsContext::cacheKey('active_prep_count'), 15, function () {
            return OpsContext::orders()
                ->whereIn('status', ['accepted', 'preparing'])
                ->count();
        });
    }

    /**
     * Get the active courier count in transit for the current store.
     */
    public static function getActiveTransitCount(): int
    {
        return Cache::remember(OpsContext::cacheKey('active_transit_count'), 15, function () {
            return OpsContext::orders()
                ->where('status', 'delivery')
                ->count();
        });
    }

    /**
     * Get the pending accept count for the current store.
     */
    public static function getPendingAcceptCount(): int
    {
        return Cache::remember(OpsContext::cacheKey('pending_accept_count'), 15, function () {
            return OpsContext::orders()
                ->where('status', 'new')
                ->count();
        });
    }

    /**
     * Get KitchenState Domain Object (cached per store).
     */
    public static function getKitchenState(): KitchenState
    {
        return Cache::remember(OpsContext::cacheKey('kitchen_state_object'), 15, function () {
            return new KitchenState(self::getActivePrepCount());
        });
    }

    /**
     * Get CourierPressure Domain Object (cached per store).
     */
    public static function getCourierPressure(): CourierPressure
    {
        return Cache::remember(OpsContext::cacheKey('courier_pressure_object'), 15, function () {
            return new CourierPressure(self::getActiveTransitCount());
        });
    }

    /**
     * Get the pending accept state map (cached per store).
     */
    public static function getOperatorStateMap(): array
    {
        return Cache::remember(OpsContext::cacheKey('operator_state_map'), 15, function () {
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
