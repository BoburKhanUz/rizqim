<?php

namespace App\Services\Ops;

use App\Models\ProductVariant;
use App\Models\OrderItem;
use App\Domain\Ops\InventoryRisk;
use Illuminate\Support\Facades\Cache;

class InventoryVelocityService
{
    /**
     * Get the InventoryRisk domain object for a specific variant (cached per variant per store).
     */
    public static function getInventoryRisk(ProductVariant $record): InventoryRisk
    {
        $storeId = OpsContext::getStoreId();
        $cacheKey = "ops:inventory_risk:store_{$storeId}_variant_{$record->id}";
        
        return Cache::remember($cacheKey, 15, function () use ($record, $storeId) {
            $totalSold24h = OrderItem::where('product_variant_id', $record->id)
                ->whereHas('order', fn($q) => $q->where('store_id', $storeId))
                ->where('created_at', '>=', now()->subHours(24))
                ->sum('quantity');

            $hourlyRate = $totalSold24h / 24.0;
            return new InventoryRisk($record->stock, $hourlyRate);
        });
    }

    /**
     * Get the dynamic top selling product variant scoped to store context (a branch manager's branch).
     */
    public static function getTopSellingProductToday(): array
    {
        return Cache::remember(OpsContext::cacheKey('top_selling_product_today'), 15, function () {
            $topItem = OrderItem::whereDate('created_at', today())
                ->whereHas('order', fn ($q) => OpsContext::scopeOrders($q))
                ->select('product_name')
                ->selectRaw('SUM(quantity) as qty')
                ->groupBy('product_name')
                ->orderBy('qty', 'desc')
                ->first();

            return [
                'name' => $topItem ? $topItem->product_name : 'No sales today',
                'qty' => $topItem ? $topItem->qty : 0,
                'desc' => $topItem ? "{$topItem->qty} units sold today" : 'Stable'
            ];
        });
    }

    /**
     * Count low stock variants for the store.
     */
    public static function getLowStockAlertsCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:low_stock_alerts_count:store_{$storeId}", 15, function () use ($storeId) {
            return ProductVariant::whereHas('product', fn($q) => $q->where('store_id', $storeId))
                ->where('stock', '>', 0)
                ->where('stock', '<', 10)
                ->count();
        });
    }

    /**
     * Get comma-separated list of top low-stock variants scoped to store context.
     */
    public static function getLowStockNamesString(int $limit = 3): string
    {
        $storeId = OpsContext::getStoreId();
        $cacheKey = "ops:low_stock_names_string:store_{$storeId}_limit_{$limit}";
        
        return Cache::remember($cacheKey, 15, function () use ($limit, $storeId) {
            $names = ProductVariant::whereHas('product', fn($q) => $q->where('store_id', $storeId))
                ->where('stock', '>', 0)
                ->where('stock', '<', 10)
                ->with('product')
                ->get()
                ->map(fn($v) => $v->product->name ?? $v->name)
                ->filter()
                ->unique()
                ->take($limit)
                ->join(', ');

            return $names ?: 'None';
        });
    }

    /**
     * Count out of stock variants for the store.
     */
    public static function getOutOfStockCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:out_of_stock_count:store_{$storeId}", 15, function () use ($storeId) {
            return ProductVariant::whereHas('product', fn($q) => $q->where('store_id', $storeId))
                ->where('stock', '<=', 0)
                ->count();
        });
    }
}
