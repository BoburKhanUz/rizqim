<?php

namespace App\Filament\Widgets;

use App\Services\Ops\PaymentInsightService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $avgValue = PaymentInsightService::getAverageOrderValue();
        $avgFormatted = number_format($avgValue, 0, '.', ',') . ' UZS';

        $dominance = PaymentInsightService::getPaymentMethodDominance();
        $failedCount = PaymentInsightService::getFailedTransactionsCount();
        $profitability = PaymentInsightService::getRushHourProfitability();

        $failedDesc = $failedCount > 0 ? "{$failedCount} transactions rejected" : '100% gateway success rate';
        $failedColor = $failedCount > 0 ? 'danger' : 'success';

        return [
            Stat::make('Average Order Value', $avgFormatted)
                ->description($profitability['desc'])
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            Stat::make('Payment Preference', $dominance['val'])
                ->description($dominance['desc'])
                ->color($dominance['color']),
                
            Stat::make('Failed Transactions', "{$failedCount} Failed")
                ->description($failedDesc)
                ->color($failedColor),
        ];
    }
}
