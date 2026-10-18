<?php

namespace App\Services\Ops;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class PaymentInsightService
{
    /**
     * Get average order value scoped to current store context.
     */
    public static function getAverageOrderValue(): float
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:average_order_value:store_{$storeId}", 15, function () use ($storeId) {
            return (float) Order::where('store_id', $storeId)->avg('total_price');
        });
    }

    /**
     * Get payment gateway preference dominance and percentages scoped to store context.
     */
    public static function getPaymentMethodDominance(): array
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:payment_method_dominance:store_{$storeId}", 15, function () use ($storeId) {
            $methods = Order::where('store_id', $storeId)
                ->select('payment_method')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('payment_method')
                ->orderBy('count', 'desc')
                ->get();

            $totalOrders = Order::where('store_id', $storeId)->count();
            
            if ($totalOrders > 0 && $methods->isNotEmpty()) {
                $dominant = $methods->first();
                $dominantName = strtoupper($dominant->payment_method);
                $dominantPct = round(($dominant->count / $totalOrders) * 100);

                return [
                    'val' => "{$dominantName} Dominant",
                    'desc' => "{$dominantPct}% preferred by customers",
                    'color' => 'info'
                ];
            }

            return [
                'val' => 'No transactions',
                'desc' => 'Awaiting payment data',
                'color' => 'gray'
            ];
        });
    }

    /**
     * Get failed transactions count scoped to store context.
     */
    public static function getFailedTransactionsCount(): int
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:failed_transactions_count:store_{$storeId}", 15, function () use ($storeId) {
            return Order::where('store_id', $storeId)->where('status', 'canceled')->count();
        });
    }

    /**
     * Compute Rush Hour Profitability (Lunch check avg vs Late Night check avg) scoped to store context.
     */
    public static function getRushHourProfitability(): array
    {
        $storeId = OpsContext::getStoreId();
        
        return Cache::remember("ops:rush_hour_profitability:store_{$storeId}", 15, function () use ($storeId) {
            $orders = Order::where('store_id', $storeId)->select('created_at', 'total_price')->get();

            $lunchChecks = [];
            $nightChecks = [];

            foreach ($orders as $order) {
                $hour = (int) $order->created_at->format('H');

                if ($hour >= 12 && $hour <= 14) {
                    $lunchChecks[] = (float) $order->total_price;
                } elseif ($hour >= 22 || $order->created_at->format('H') <= 2) {
                    $nightChecks[] = (float) $order->total_price;
                }
            }

            $lunchAvg = count($lunchChecks) > 0 ? (array_sum($lunchChecks) / count($lunchChecks)) : 0;
            $nightAvg = count($nightChecks) > 0 ? (array_sum($nightChecks) / count($nightChecks)) : 0;

            $lunchFormatted = number_format($lunchAvg, 0, '.', ',') . ' UZS';
            $nightFormatted = number_format($nightAvg, 0, '.', ',') . ' UZS';

            return [
                'lunch_avg' => $lunchAvg,
                'lunch_formatted' => $lunchFormatted,
                'night_avg' => $nightAvg,
                'night_formatted' => $nightFormatted,
                'desc' => "Lunch (12-14): {$lunchFormatted} | Night (22-02): {$nightFormatted}"
            ];
        });
    }
}
