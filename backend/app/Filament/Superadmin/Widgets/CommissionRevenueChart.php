<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Order;
use App\Enums\OrderStatus;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class CommissionRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue (Oxirgi 30 kun)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        // Loop through the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('d M');

            // Sum commission for that day
            $sum = Order::where('status', OrderStatus::DELIVERED->value)
                ->whereDate('created_at', $date)
                ->sum('commission_amount');

            $data[] = $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Komissiya Daromadi (UZS)',
                    'data' => $data,
                    'borderColor' => '#f59e0b', // Amber to match superadmin theme
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
