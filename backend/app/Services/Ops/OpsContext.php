<?php

namespace App\Services\Ops;

use App\Filament\Support\StorePanelScope;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class OpsContext
{
    protected static ?int $storeId = null;

    /**
     * Order-derived metrics: cached per store, and per branch for a branch manager.
     */
    public const ORDER_METRICS = [
        'delayed_orders_count', 'active_prep_count', 'active_transit_count', 'pending_accept_count',
        'kitchen_state_object', 'courier_pressure_object', 'operator_state_map', 'top_selling_product_today',
        'average_order_value', 'payment_method_dominance', 'failed_transactions_count', 'rush_hour_profitability',
    ];

    /**
     * Explicitly set the active store ID for this request or process.
     */
    public static function setStoreId(?int $storeId): void
    {
        self::$storeId = $storeId;
    }

    /**
     * Reset the active store context to prevent memory leaks in long-running
     * environments like Queue Workers, RoadRunner, or Octane.
     */
    public static function forget(): void
    {
        self::$storeId = null;
    }

    /**
     * Run a closure in an isolated scoped store context and safely reset it back.
     *
     * @param int|null $storeId
     * @param callable $callback
     * @return mixed
     */
    public static function run(?int $storeId, callable $callback): mixed
    {
        $previousStoreId = self::$storeId;
        self::setStoreId($storeId);

        try {
            return $callback();
        } finally {
            self::setStoreId($previousStoreId);
        }
    }

    /**
     * Get the active store ID: the one set explicitly, else the store panel's tenant (a super admin has no store of
     * their own), else the signed-in staff member's store. Never a fallback store: without one there is nothing to show.
     */
    public static function getStoreId(): ?int
    {
        if (self::$storeId !== null) {
            return self::$storeId;
        }

        return StorePanelScope::storeId();
    }

    /**
     * The branch the figures are narrowed to: a branch manager's own (as OrderPolicy and the API's order lists do);
     * an explicitly set store covers the whole store.
     */
    public static function getBranchId(): ?int
    {
        return self::$storeId !== null ? null : StorePanelScope::branchId();
    }

    /**
     * Narrow an orders query (also the one inside a whereHas('order')) to the current context.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function scopeOrders(Builder $query): Builder
    {
        return $query->where('orders.store_id', static::getStoreId())
            ->when(static::getBranchId(), fn (Builder $query, int $branchId) => $query->where('orders.branch_id', $branchId));
    }

    /**
     * The orders the current context covers.
     *
     * @return Builder<Order>
     */
    public static function orders(): Builder
    {
        return static::scopeOrders(Order::query());
    }

    /**
     * Cache key of an order-derived metric for the current context.
     */
    public static function cacheKey(string $metric): string
    {
        return static::metricKey($metric, static::getStoreId(), static::getBranchId());
    }

    public static function metricKey(string $metric, ?int $storeId, ?int $branchId = null): string
    {
        return "ops:{$metric}:store_{$storeId}" . ($branchId ? "_branch_{$branchId}" : '');
    }

    /**
     * Drop an order's cached metrics: its store's, and its branch's (also the branch it was moved from).
     */
    public static function forgetOrderMetrics(Order $order): void
    {
        $storeId = $order->store_id;
        if (!$storeId) {
            return;
        }

        $branchIds = array_unique(array_filter([$order->branch_id, $order->getOriginal('branch_id')]));

        foreach (self::ORDER_METRICS as $metric) {
            Cache::forget(static::metricKey($metric, (int) $storeId));

            foreach ($branchIds as $branchId) {
                Cache::forget(static::metricKey($metric, (int) $storeId, (int) $branchId));
            }
        }
    }
}
