<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class PlatformOrdersChart extends ChartWidget
{
    protected ?string $heading = 'Buyurtmalar soni (Oxirgi 30 kun)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('d M');

            $count = Order::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Buyurtmalar soni',
                    'data' => $data,
                    'borderColor' => '#3b82f6', // Blue color
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
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
