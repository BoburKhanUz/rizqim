<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;

class PaymentInsightService
{
    /**
     * Get average order value scoped to current store context.
     */
    public static function getAverageOrderValue(): float
    {
        return Cache::remember(OpsContext::cacheKey('average_order_value'), 15, function () {
            return (float) OpsContext::orders()->avg('total_price');
        });
    }

    /**
     * Get payment gateway preference dominance and percentages scoped to store context.
     */
    public static function getPaymentMethodDominance(): array
    {
        return Cache::remember(OpsContext::cacheKey('payment_method_dominance'), 15, function () {
            $methods = OpsContext::orders()
                ->select('payment_method')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('payment_method')
                ->orderBy('count', 'desc')
                ->get();

            $totalOrders = OpsContext::orders()->count();
            
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
        return Cache::remember(OpsContext::cacheKey('failed_transactions_count'), 15, function () {
            return OpsContext::orders()->where('status', 'canceled')->count();
        });
    }

    /**
     * Compute Rush Hour Profitability (Lunch check avg vs Late Night check avg) scoped to store context.
     */
    public static function getRushHourProfitability(): array
    {
        return Cache::remember(OpsContext::cacheKey('rush_hour_profitability'), 15, function () {
            $orders = OpsContext::orders()->select('created_at', 'total_price')->get();

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
